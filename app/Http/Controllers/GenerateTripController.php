<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use App\Services\GenerateTrip\CustomGraph;
use App\Services\GenerateTrip\DataImport;
use App\Services\GenerateTrip\DijkstraAlgorithm;

use Fhaculty\Graph\Graph ;
use Fhaculty\Graph\Vertex;
use Fhaculty\Graph\Edge\Base;
use Fhaculty\Graph\Edge\Directed ;
use Fhaculty\Graph\Attribute\AttributeBagNamespaced ;
use Fhaculty\Graph\Set\Vertices ;
use Graphp\GraphViz\GraphViz;

class GenerateTripController extends Controller
{
    public static function efficientShuffle(&$array)
    {
        $size = count($array);
        for ($k = 0; $k < $size; $k++) {
            $j = mt_rand($k, $size - 1);
            $temp = $array[$k];
            $array[$k] = $array[$j];
            $array[$j] = $temp;
        }
    }

    public static function selectRandomTypes($userplaces, $Rest, $economicsituation, $previoustypeselected, $y)              // A function for performs random selection and ensures diversity
    {

        if($economicsituation == "green") {
            $number_of_choices = count($userplaces)  ;
        }
        if($economicsituation == "orange") {
            $number_of_choices = count($userplaces) - 1;
        }
        if($economicsituation == "red" ||  count($userplaces) < 3) {
            $number_of_choices = 2;
        }

        $selected_types = array();
        $selected_types = array_diff($userplaces, $previoustypeselected);
        $selected_types = array_values($selected_types);
        $reminder = count($selected_types) - $number_of_choices;

        if ($reminder < 0) {
            $reminder = abs($reminder);
            while($reminder > 0) {
                $randomindex = random_int(0, count($previoustypeselected) - 1);
                if (!in_array($previoustypeselected[$randomindex], $selected_types)) {
                    array_push($selected_types, $previoustypeselected[$randomindex]);
                    $reminder--;
                }
            }
        }

        return $selected_types;
    }

    public static function haversineDistance($source, $destination)      // A function for calculate a haversineDistance between two point
    {
        $latFrom = deg2rad($source['latitude']);
        $lonFrom = deg2rad($source['longitude']);
        $latTo = deg2rad($destination['latitude']);
        $lonTo = deg2rad($destination['longitude']);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        $distance = $angle *  6371;
        return $distance;
    }

    public function generate(Request $request)       // A function for generate the trip
    {
        try {
            $Data = $request->all();
            $user_id = $Data['user_id'];
            $fromCity = $Data['fromcity'];
            $toCountry = $Data['tocountry'];
            $numberOfDays = $Data['N.days'];
            $numberOfPeople = $Data['N.people'];
            $preferedplaces = $Data['preferedplaces'];
            $preferedfood = $Data['preferedfood'];
            $Date = $Data['date'];
            $selectedplaces = $Data['places'];
            if($Data['totalBudget'] == "Minimum") {
                $Budget = 500;
            }
            if($Data['totalBudget'] == "Open") {
                $Budget = PHP_INT_MAX;
            } if($Data['totalBudget'] != "Minimum" && $Data['totalBudget'] != "Open") {
                $Budget = $Data['totalBudget'];
            }

            $days = 1;
            $cityindex = 0;
            $ticketprice = 0;
            $Totalcost = 0;
            $EconomicSituation = "green";
            $rest = false;
            $previoustype = [];
            $visited = [];
            $response = [];       // response Array Initialization
            $requiredFields = ['user_id','fromcity','tocountry','N.days','N.people','preferedplaces','preferedfood','date','totalBudget','places'
            ];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (empty($Data[$field])) {
                    $missingFields[] = $field;
                }
            }
            if ($Budget < 500) {
                return response()->json(
                    ['error' => "Minimum Budget is 500 "],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!empty($missingFields)) {
                return response()->json(
                    ['error' => "Missing Required Field"],
                    Response::HTTP_BAD_REQUEST
                );
            }
            if (count($preferedplaces) < 2) {
                return response()->json(
                    ['error' => "You Can't Select Less Than 2 Types Of Places "],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ($numberOfDays > 26) {
                return response()->json(
                    ['error' => "Maximum Number Of Days is 26 "],
                    Response::HTTP_BAD_REQUEST
                );
            }
            if ($numberOfPeople > 30) {
                return response()->json(
                    ['error' => "Maximum Nummber Of People is 30"],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $userExists = DB::table('user')->where('id', $user_id)->exists();
            if (!$userExists) {
                return response()->json(
                    ['error' => "User With Id {$user_id} Doesn't Exist."],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // fetch cities of the desination country
            $countrycitiesQuery = DB::table('City')
            ->join('country', 'City.country', '=', 'country.country_name')
            ->select('City.name as name', "city.capital")
            ->where('country.country_name', '=', $toCountry)
            ->get()->toArray();

            if (empty($countrycitiesQuery)) {
                return response()->json(
                    ['error' => "'$toCountry' Is Not Supported"],
                    Response::HTTP_NOT_FOUND
                );
            }

            $countrycities1['name'] = array_column($countrycitiesQuery, 'name');          // array include the city
            $countrycities2['capital'] = array_column($countrycitiesQuery, 'capital');       // array include numbers which indicate if the city is a capital


            $countrycities = array_combine($countrycities1['name'], $countrycities2['capital']);    // array include cities of the desination country as a key and numbers which indicate if this city is a capital as value
            $N_cities = count($countrycities1['name']);    // number of country cities

            $cityNumberOfDays = floor($numberOfDays / $N_cities);           // number of day in each city in the country
            $reminderdays = $numberOfDays % $N_cities;

            $city1NumberOfDays = $cityNumberOfDays + $reminderdays;

            if($numberOfDays < $N_cities) {
                $cityNumberOfDays = 1;
                $city1NumberOfDays = 1;
            }
            $cities = array_keys($countrycities);
            $City_name = $cities[0];

            for ($i = 1 ; $i <= $numberOfDays ;$i++) {         // loop for every day in the trip
                if($i > $city1NumberOfDays) {
                    $days += 1;
                }
                $onlyday = true;
                $changecity = false;
                $travelmethod = null;
                $TravelCost = 0;
                $time = 0;
                $userplacestime = [];

                $BudgetOfDay = floor(($Budget - $Totalcost) / ($numberOfDays - ($i - 1)));

                if ($i == 1) {        // Day1
                    $date = $Date ;
                    $destinationcityname = array_search(1, $countrycities);            // find the capital

                    $destinationcityQuery = DB::table('City')->select('*')->where('name', $destinationcityname)->first();     // fetch the destination city information from database
                    $destinationcity = $destinationcityQuery ? get_object_vars($destinationcityQuery) : null; // to convert stdclass object to array
                    $sourcecityQuery = DB::table('City')->select('*')->where('name', $fromCity)->first();     // fetch the source city information from database
                    $sourcecity = $sourcecityQuery ? get_object_vars($sourcecityQuery) : null;  // to convert stdclass object to array
                    if (empty($sourcecity)) {
                        return response()->json(
                            ['error' => "'$fromCity' City Is Not Supported"],
                            Response::HTTP_NOT_FOUND
                        );
                    }
                    $distance = self::haversineDistance($sourcecity, $destinationcity);
                    $travelmethod = CustomGraph::travell_method($distance) ;
                    $ticketprice = $distance * 0.09 ;
                    $TravelCost = $ticketprice * $numberOfPeople;
                    $BudgetOfDay -= $TravelCost;

                    if($city1NumberOfDays > 1) {
                        $rest = true;
                        $onlyday  = false;
                    }

                    // insert trip info into trip table
                    $tripid = DB::table('trip')->insertGetId(
                        ['country' => $toCountry,
                        'user_id' =>  $user_id,
                        'from_city' => $sourcecity['city_id'],
                        'budget' => $Data['totalBudget'],
                        'number_of_people' => $numberOfPeople,
                        'number_of_days' => $numberOfDays,
                        'transportation' => $travelmethod,
                        ]
                    );

                    // fill the response
                    $response['trip_id'] = $tripid;
                    $response['date'] = date("Y-m-d", strtotime($Date));
                    $response['fromCity'] = $fromCity;
                    $response['destination'] = $toCountry;
                    $response['totalBudget'] = $Data['totalBudget'];
                    $response['numberOfPeople'] = $numberOfPeople;

                } else { //other days
                    $date = date("Y-m-d", strtotime($date . ' +1 day'));

                    if(($i % $city1NumberOfDays == 1) || (($days % $cityNumberOfDays == 0 && $days != 0) && $i > $city1NumberOfDays)) { // check if we have to change city
                        $changecity = true;
                        $sourcecityQuery = DB::table('City')->select('*')->where('name', $City_name)->first();     // fetch the source city information from database
                        $sourcecity = get_object_vars($sourcecityQuery);  // to convert stdclass object to array
                        $cityindex += 1;
                        $City_name = $cities[$cityindex];
                        $destinationcityQuery = DB::table('City')->select('*')->where('name', $City_name)->first();     // fetch the destination city information from database
                        $destinationcity = get_object_vars($destinationcityQuery);  // to convert stdclass object to array
                        $distance = self::haversineDistance($sourcecity, $destinationcity);
                        $travelmethod = CustomGraph::travell_method($distance) ;
                        if($travelmethod == "plane") {
                            $ticketprice = $distance * 0.09 ;
                            $TravelCost = $ticketprice * $numberOfPeople;
                        } else {
                            $TravelCost = $distance * 1.6;  // distance * cost of 1 litre fuel
                        }
                        $BudgetOfDay -= $TravelCost ;
                        if($cityNumberOfDays > 1) {
                            $rest = true;
                            $onlyday  = false;
                        }

                    } else {
                        $BudgetOfDay -= $TravelCost;

                    }
                }
                if($changecity || $i == 1) {
                    $index = 1;
                } else {
                    $index = 0;
                }
                if($i == 1) {
                    $root = null;
                    $EconomicSituation = "orange";
                } else {
                    $root = $hotelAttributes;
                }


                $custompreferedplaces = self::selectRandomTypes($preferedplaces, $rest, $EconomicSituation, $previoustype, $i);


                foreach($selectedplaces[ $City_name] as $citychoosentypes => $citychoosenplaces) {

                    if($citychoosentypes === "Resturants" || $citychoosentypes === "Hotels") {
                        continue;
                    } else {

                        if(!empty($citychoosenplaces)) {

                            foreach($citychoosenplaces as $citychoosenplace) {

                                if(!in_array($citychoosenplace['name'], $visited)) {
                                    $userplacestime[$citychoosentypes] = $citychoosenplace['time'];
                                    if(!in_array($citychoosentypes, $custompreferedplaces)) {
                                        array_unshift($custompreferedplaces, $citychoosentypes);
                                    } else {
                                        $key1 = array_search($citychoosentypes, $custompreferedplaces);
                                        if ($key1 !== false && $key1 >  0) {
                                            unset($custompreferedplaces[$key1]);
                                            $custompreferedplaces = array_values($custompreferedplaces);
                                            array_unshift($custompreferedplaces, $citychoosentypes);

                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                // return $userplacestime;
                if(array_sum($userplacestime) >= 4) {
                    $custompreferedplaces = array_keys($userplacestime);
                    if($onlyday == false) {
                        $half_size = ceil(count($custompreferedplaces) / 2);
                        $custompreferedplaces = array_slice($custompreferedplaces, 0, $half_size);

                    }
                };

                $places_day = DataImport::importData($custompreferedplaces, $Data, $City_name, $travelmethod, $BudgetOfDay, $numberOfPeople, $visited, $selectedplaces);
                //return $places_day;
                $graph = new Graph();
                $graph1 = CustomGraph::buildGraph($places_day, $graph, $changecity, $root, $places_day['preferred']);      // create A custom graph which contain the Possible paths for USER:
                //     $graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                //    $graphviz->display($graph2);
                $sourceNode = $graph1->getVertex(0);
                $sourcNodeType = $sourceNode->getAttribute('name');
                list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);
                $path = end($paths);

                $currenthotel = $graph1->getvertex($path[$index]);
                $nodeAttributes = $currenthotel->getAttributeBag();
                $hotelAttributes = $nodeAttributes->getAttributes();

                $previoustype =  $custompreferedplaces;

                // calculate time
                /*     $totalTime = 0;
                     $newnodes_att = [];
                     $previoustype = $custompreferedplaces;

                     //add limit for time of the day trip

                     foreach ($path as $node_id) {

                         $node = $graph1->getvertex($node_id);
                         $newnode_att_bag = $node->getAttributeBag();
                         $newnode_att = $newnode_att_bag->getAttributes();
                         $nodetime = $node->getAttribute('time');
                         $totalTime += $nodetime;

                         $newnodes_att[] = $newnode_att;

                     }

                     $s = 1;
                     $m = 0;
                     $organizedPlaces = [];
                     foreach ($newnodes_att  as $type => $place) {
                         if($place['placeType'] == 'Hotel') {
                             $organizedPlaces['Hotels'][] =  $place;
                         } elseif($place['placeType'] == 'Resturant' && $s == 3) {
                             $organizedPlaces['Resturants1'][] =  $place;
                         } elseif($place['placeType'] == 'Resturant' && $s == count($newnodes_att)) {
                             $organizedPlaces['Resturants2'][] =  $place;
                         } else {
                             $organizedPlaces[$place['placeType']][] = $place;
                         }
                         $s++;
                     }


                     $nonRemovableTypes = ['Resturant', 'Hotel','Airport'];
                     $newtotaltime = $totalTime;

                     //      while($newtotaltime >= 10) {
                     $organizedPlaces =   array_reverse($organizedPlaces);
                     //   $organizedPlaces = array_values($organizedPlaces);

                     foreach ($organizedPlaces as $key => $place) {
                         $found = false;
                         if (!in_array($place[0]['placeType'], $nonRemovableTypes)) {
                             foreach($selectedplaces[$City_name][$key] as $citychoosenplaces) {
                                 if($citychoosenplaces['name'] == $place[0]['name']) {
                                     $found = true ;
                                 }
                             }
                             if($found == true) {
                                 continue;
                             }
                             unset($places_day[ $key]);
                             foreach ($custompreferedplaces as $key1 => $val) {
                                 if ($val == $key) {
                                     unset($custompreferedplaces[$key1]);
                                 }
                             }

                             $newtotaltime -= $place[0]['time'];
                             if($newtotaltime <= 14) {
                                 break;
                             } else {
                                 continue;
                             }

                         }
                     }

                     $custompreferedplaces = array_values($custompreferedplaces);

                     $previoustype = $custompreferedplaces;



                     $graph = new Graph();
                     $graph1 = CustomGraph::buildGraph($places_day, $graph, $changecity, $newnodes_att[0], $custompreferedplaces);      // create A custom graph which contain the Possible paths for USER:
                     $sourceNode = $graph1->getVertex(0);

                     list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);     // execute Dijkstra Algorithm on the created graph
                     $path = end($paths);
                     foreach($path as $visitednodes) {
                         $visitednode = $graph1->getvertex($visitednodes);
                         $nodetype = $visitednode->getAttribute('placeType');
                         $nodename = $visitednode->getAttribute('name');
                         $visited[] = $nodename;
                     }*/

                // calculate the total cost of all nodes
                $cost = $TravelCost;

                if($i == 1 || $changecity == true) {
                    $shiftfirstnode = true;
                }
                foreach ($path as $node_id) {
                    if($shiftfirstnode == true) {
                        $shiftfirstnode = false;
                        continue;
                    }
                    $node = $graph1->getvertex($node_id);
                    $nodeprice = $node->getAttribute('price');

                    $nodetype = $node->getAttribute('placeType');

                    if(reset($path) != $node_id) {
                        $preedges = $node->getEdgesIn();
                        foreach($preedges as $preedge) {
                            $edgetravelmethod =  $preedge->getAttribute('travelMethod');
                            $edgedistance =  $preedge->getAttribute('distance');
                            if($edgetravelmethod == "car") {
                                $cost += $edgedistance * 1.6; // 1.6 is avg of Taxi cost in 1KM
                            }
                        }
                    }

                    if($nodetype  == "Hotel") {
                        $cost += $nodeprice *  (($numberOfPeople / 2) + ($numberOfPeople % 2));

                    } else {
                        $cost += $nodeprice * $numberOfPeople;

                    }
                }
                $trip_days["day_" . $i]['EconomicSituation'] = $EconomicSituation;
                $Budget_difference = ($BudgetOfDay + $TravelCost) - ceil($cost);

                if($Budget_difference > 75) {
                    $EconomicSituation = "green";
                } elseif($Budget_difference < -75) {
                    $EconomicSituation = "red";
                } else {
                    $EconomicSituation = "orange";
                }


                // insert trip info into tripday table
                $dayid = DB::table('tripday')->insertGetId(
                    ['trip_id' => $tripid,
                    'city_id' =>  $places_day['Currentcity']['city_id'],
                    'date' => $date,
                    'hotel_id' => $hotelAttributes['id'],
                    'transportaition_method' => $travelmethod,
                    ]
                );

                $trip_days["day_" . $i]['dayId'] = $dayid;

                $trip_days["day_" . $i]['custompreferedplaces'] = $custompreferedplaces;


                $trip_days["day_" . $i]['date'] = $date;
                $trip_days["day_" . $i]['city'] = $places_day['Currentcity'];
                $trip_days["day_" . $i]['neededMony'] = ceil($cost) ;
                $Totalcost += $cost ;

                // flightReservation
                if($travelmethod == "plane") {
                    if($i == 1) {
                        $travelfromcity = $fromCity;
                    } else {
                        $travelfromcity = $sourcecity['name'];
                    }
                    $response['flightReservation']["day_" . $i] = [ "airportId" => $places_day['Airport'][0]['id'] ,"fromCity" => $travelfromcity ,
                    "airportName" => $places_day['Airport'][0]['name'],"address" => $places_day['Airport'][0]['address'],
                    "price" => ceil($ticketprice) ,"toatlAmountOfMony" => ceil($ticketprice * $Data['N.people']) ,"location" => $places_day['Airport'][0]['location']
                      ];
                }

                // "hotelReservation"
                if($i == 1 || $changecity == true) {
                    $hotelnode = $graph1-> getvertex($path[1]);    // get hotel node by id

                    $hotelAttr = $hotelnode->getAttributeBag();  // get the attributes bag
                    $response['hotelReservation']["day_" . $i] = $hotelAttr->getAttributes();   // get the hotel node attribute
                }

                // places of day:
                $dayPlaces = [];
                $Resturantsofday = [];
                $n = 0;
                $isFirstnode = true;

                foreach ($path as $key => $nodeid) {
                    $n += 1;
                    $node = $graph1->getvertex($nodeid);
                    $Attributes = $node->getAttributeBag();
                    $NodeAttributes = $Attributes->getAttributes();
                    $visited[] = $NodeAttributes['name'];
                    if($isFirstnode == true) {
                        $Attributes->setAttribute("transportaionMethod", $travelmethod);
                        $dayPlaces[$n] = $Attributes->getAttributes();
                    } else {
                        $edges_in = $node->getEdgesFrom($prenode);
                        foreach($edges_in as $edge_in) {
                            $dayPlaces[$n] = $Attributes->getAttributes();
                            $dayPlaces[$n]['transportaionMethod'] = $edge_in->getAttribute('travelMethod');
                            $dayPlaces[$n]['distancefromlastplace'] = ceil($edge_in->getAttribute('distance')) ;
                        }

                        // insert place into dayplaces table

                        $place_type = $dayPlaces[$n]['placeType'];

                        if($place_type == "Hotel") {

                            DB::table('dayplaces')->insert(
                                ['day_id' => $dayid,
                                'place_id' =>  $dayPlaces[$n]['id'],
                                'place_type' =>  $place_type,
                                'index' =>  $key ,
                                'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                                'money_amount' => $dayPlaces[$n]['price'] * $numberOfPeople,
                                'pre_distance' => $dayPlaces[$n]['distancefromlastplace']
                                ]
                            );


                        } elseif($place_type == "Airport") {
                            DB::table('dayplaces')->insert(
                                ['day_id' => $dayid,
                                'place_id' =>  $dayPlaces[$n]['id'],
                                'place_type' =>  $place_type,
                                'index' =>  $key ,
                                'transport_method' => "plane",
                                'money_amount' => ceil($ticketprice *  $numberOfPeople),
                                'pre_distance' => $dayPlaces[$n]['distancefromlastplace']
                                ]
                            );

                        } else {
                            DB::table('dayplaces')->insert(
                                ['day_id' => $dayid,
                                'place_id' =>  $dayPlaces[$n]['id'],
                                'place_type' =>  $place_type,
                                'index' =>  $key ,
                                'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                                'money_amount' => ceil($dayPlaces[$n]['price'] * $numberOfPeople),
                                'pre_distance' => $dayPlaces[$n]['distancefromlastplace']
                                ]
                            );
                        }

                    }

                    $isFirstnode = false;
                    $prenode = $graph1->getvertex($nodeid);

                }

                $trip_days["day_" . $i]['dayPlaces'] = $dayPlaces;

                $response['tripDays'] = $trip_days ;

            }

            $ticketprice = 0;
            $response['TotalCost'] = ceil($Totalcost) ;

            return response()->json(['trip' => $response], Response::HTTP_OK);

        } catch (\Exception $error) {
            return response()->json(
                ['error' => $error->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
