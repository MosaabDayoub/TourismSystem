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

    public static function selectRandomTypes($userplaces, $Rest, $economicsituation)              // A function for performs random selection and ensures diversity
    {
        if($economicsituation == "green") {
            $number_of_choices = count($userplaces) ;
        }
        if($economicsituation == "orange") {
            $number_of_choices = count($userplaces) - 1;
        }
        if($economicsituation == "red" ||  count($userplaces) < 3) {
            $number_of_choices = 2;
        }

        /*        if ($Rest && in_array("night", $userplaces) && count($userplaces) > 2) {
                    $userplaces = array_diff($userplaces, array("night"));
                    $userplaces = array_values($userplaces);
                    $number_of_choices -= 1;
                }*/

        // Shuffle the matrix to take random elements
        self::efficientShuffle($userplaces);
        self::efficientShuffle($userplaces);

        //   $number_of_choices = ($Rest || count($userplaces) < 3 || $economy) ? 2 : 3;

        $selected_types = array();

        for ($i = 0; $i < $number_of_choices; $i++) {
            // Ensure that types are not duplicated
            array_push($selected_types, $userplaces[$i]);
        }
        $key = array_search("night", $selected_types);


        if ($key !== false && $key < count($selected_types) - 1) {

            unset($selected_types[$key]);
            $selected_types = array_values($selected_types);
            $selected_types[] = "night";
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
                $changecity = false;
                $travelmethod = null;
                $TravelCost = 0;
                $time = 0;

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
                    }
                    $custompreferedplaces = self::selectRandomTypes($preferedplaces, $rest, $EconomicSituation, );
                    $places_day = DataImport::importData($custompreferedplaces, $Data, $destinationcityname, $travelmethod, $BudgetOfDay, $numberOfPeople, $visited, $selectedplaces, $i);        //get the data by places1; places1 is a nested array which include the prefered places for USER and the airport
                    //return $places_day;

                    $graph = new Graph();
                    $graph1 = CustomGraph::buildGraph($places_day, $graph, false, null, $custompreferedplaces);      // create A custom graph which contain the Possible paths for USER:
                    $sourceNode = $graph1->getVertex(0);
                    // $graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                    //   $graphviz->display($graph1);

                    list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);     // execute Dijkstra Algorithm on the created graph
                    $path = end($paths);


                    $currenthotel = $graph1->getvertex($path[1]);
                    $nodeAttributes = $currenthotel->getAttributeBag();
                    $hotelAttributes = $nodeAttributes->getAttributes();

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
                        }

                        $custompreferedplaces = self::selectRandomTypes($preferedplaces, $rest, $EconomicSituation);
                        $places_day = DataImport::importData($custompreferedplaces, $Data, $City_name, $travelmethod, $BudgetOfDay, $numberOfPeople, $visited, $selectedplaces, $i);        //get the data by places1; places1 is a nested array which include the prefered places for USER and the airport
                        //return $places_day;

                        $graph = new Graph();
                        $graph1 = CustomGraph::buildGraph($places_day, $graph, $changecity, $hotelAttributes, $custompreferedplaces);      // create A custom graph which contain the Possible paths for USER:
                        $sourceNode = $graph1->getVertex(0);
                        $sourcNodeType = $sourceNode->getAttribute('name');

                        //$graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                        //$graphviz->display($graph1);

                        list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);     // execute Dijkstra Algorithm on the created graph
                        $path = end($paths);

                        $currenthotel = $graph1->getvertex($path[1]);
                        $nodeAttributes = $currenthotel->getAttributeBag();
                        $hotelAttributes = $nodeAttributes->getAttributes();

                    } else {

                        $BudgetOfDay -= $TravelCost;
                        $custompreferedplaces = self::selectRandomTypes($preferedplaces, $rest, $EconomicSituation);

                        $places_day = DataImport::importData($custompreferedplaces, $Data, $City_name, $travelmethod, $BudgetOfDay, $numberOfPeople, $visited, $selectedplaces, $i);
                        //return $places_day;
                        $graph = new Graph();
                        $graph1 = CustomGraph::buildGraph($places_day, $graph, $changecity, $hotelAttributes, $custompreferedplaces);      // create A custom graph which contain the Possible paths for USER:

                        //     $graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                        //    $graphviz->display($graph2);
                        $sourceNode = $graph1->getVertex(0);
                        $sourcNodeType = $sourceNode->getAttribute('name');
                        list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);
                        $path = end($paths);

                        $currenthotel = $graph1->getvertex($path[0]);
                        $nodeAttributes = $currenthotel->getAttributeBag();
                        $hotelAttributes = $nodeAttributes->getAttributes();
                    }
                }

                //add limit for time of the day trip
                $end = ["id" => 1234567,"name" => "destination",
                "lon" => 700.251828422382225,
                "lat" => 800.803499350173176,
                "price" => 0,
                "placeType" => null,
                ];

                $newnodes_att = [];
                $time = 0;
                $n = 0;
                $f = 0;
                $resturantlevel = [];

                foreach ($path as $node_id) {
                    $node = $graph1->getvertex($node_id);

                    $nodetime = $node->getAttribute('time');
                    $time += $nodetime;
                    if ($time > 10) {
                        break;
                    }
                    $newnode_att_bag = $node->getAttributeBag();
                    $newnode_att = $newnode_att_bag->getAttributes();
                    $newnodes_att[] = $newnode_att;
                }
                $graph1 = new Graph();
                ${"newvertex" . $n} = CustomGraph::createNode($graph1, $newnodes_att[0]);

                $first = true;
                foreach($newnodes_att as $newplace_att) {
                    if ($first) {
                        $first = false;
                        continue;
                    } else {
                        $n++;
                        ${"newvertex" . $n}  = CustomGraph::createNode($graph1, $newplace_att);
                        CustomGraph::addWeightedEdge(${"newvertex" . ($n - 1)}, ${"newvertex" . $n});

                    }
                }


                foreach($places_day['Resturants2'] as $resurant) {
                    $f++;
                    ${"resurantnode" . $f}  = CustomGraph::createNode($graph1, $resurant);
                    $resturantlevel[] = ${"resurantnode". $f} ;
                    CustomGraph::addWeightedEdge(${"newvertex" . $n}, ${"resurantnode" . $f});

                }
                $endnode = CustomGraph::createNode($graph1, $end);

                foreach($resturantlevel as $resturantnode) {

                    CustomGraph::addWeightedEdge($resturantnode, $endnode);
                }
                // $graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                //$graphviz->display($graph1);
                list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $newvertex0);
                $path = end($paths);

                foreach($path as $visitednodes) {
                    $visitednode = $graph1->getvertex($visitednodes);
                    $nodetype = $visitednode->getAttribute('placeType');
                    $nodename = $visitednode->getAttribute('name');
                    $visited[] = $nodename;
                }

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

            if($i > $city1NumberOfDays) {
                $days += 1;
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
