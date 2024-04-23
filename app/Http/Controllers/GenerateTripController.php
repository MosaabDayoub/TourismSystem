<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use App\Services\GenerateTrip\CustomGraph;
use App\Services\GenerateTrip\Data;

use Fhaculty\Graph\Graph ;
use Fhaculty\Graph\Vertex;
use Fhaculty\Graph\Edge\Base;
use Fhaculty\Graph\Edge\Directed ;
use Fhaculty\Graph\Attribute\AttributeBagNamespaced ;
use Fhaculty\Graph\Set\Vertices ;
use Graphp\GraphViz\GraphViz;
use App\Services\GenerateTrip\DijkstraAlgorithm;

class GenerateTripController extends Controller
{
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
        $Data = $request->all();
        $user_id = $Data['user_id'];
        $fromCity = $Data['fromcity'];
        $toCountry = $Data['tocountry'];
        $numberOfDays = $Data['N.days'];
        $numberOfPeople = $Data['N.people'];
        $preferedplaces = $Data['preferedplaces'];
        $preferedfood = $Data['preferedfood'];
        $PriceIsImportant = $Data['PriceIsImportant'];
        // $i = 1;
        // return $Data['places']['roma']['night'][$i];
        // fetch cities of the desination country
        $countrycitiesQuery = DB::table('City')
        ->join('country', 'City.country', '=', 'country.country_name')
        ->select('City.name as name', "city.capital")
        ->where('country.country_name', '=', $Data['tocountry'])
        ->get()->toArray();


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
        $days = 1;
        $cityindex = 0;
        $cities = array_keys($countrycities);
        $City_name = $cities[0];
        $ticketprice = 0;
        $Totalcost = 0;
        $resturantofday = 0;
        $visited = [];
        $response = [];       // response Array Initialization

        for ($i = 1 ; $i <= $numberOfDays ;$i++) {         // loop for every day in the trip
            $changecity = false;
            $travelmethod = null;

            if ($i == 1) {        // Day1
                $Cityday = 0;
                $date = $Data['date'] ;
                $destinationcityname = array_search(1, $countrycities);            // find the capital

                $destinationcityQuery = DB::table('City')->select('*')->where('name', $destinationcityname)->first();     // fetch the destination city information from database
                $destinationcity = get_object_vars($destinationcityQuery);  // to convert stdclass object to array
                $sourcecityQuery = DB::table('City')->select('*')->where('name', $fromCity)->first();     // fetch the source city information from database
                $sourcecity = get_object_vars($sourcecityQuery);  // to convert stdclass object to array
                $distance = self::haversineDistance($sourcecity, $destinationcity);
                $travelmethod = CustomGraph::travell_method($distance) ;
                $ticketprice = $distance * 0.08 ;

                $places_day = Data::fetchData($preferedplaces, $Data, $destinationcityname, $travelmethod);        //get the data by places1; places1 is a nested array which include the prefered places for USER and the airport
                $graph = new Graph();
                $graph1 = CustomGraph::buildGraph($places_day, $graph, false, null, $Data['PriceIsImportant'], $Cityday, $Data['places'], $resturantofday);      // create A custom graph which contain the Possible paths for USER:
                $sourceNode = $graph1->getVertex(0);

                //$graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                //$graphviz->display($graph1);

                list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);     // execute Dijkstra Algorithm on the created graph
                $path = end($paths);

                $currenthotel = $graph1->getvertex($path[1]);
                $nodeAttributes = $currenthotel->getAttributeBag();
                $hotelAttributes = $nodeAttributes->getAttributes();

                // insert trip info to trip table
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
                $response['date'] = date("Y-m-d", strtotime($Data['date']));
                $response['fromCity'] = $Data['fromcity'];
                $response['destination'] = $Data['tocountry'];
                $response['totalBudget'] = $Data['totalBudget'];
                $response['numberOfPeople'] = $Data['N.people'];

            } else { //other days
                $date = date("Y-m-d", strtotime($date . ' +1 day'));

                if(($i % $city1NumberOfDays == 1) || (($days % $cityNumberOfDays == 0 && $days != 0) && $i > $city1NumberOfDays)) { // check if we have to change city
                    $Cityday = 0;
                    $resturantofday += 2;
                    $changecity = true;
                    $sourcecityQuery = DB::table('City')->select('*')->where('name', $City_name)->first();     // fetch the source city information from database
                    $sourcecity = get_object_vars($sourcecityQuery);  // to convert stdclass object to array
                    $cityindex += 1;
                    $City_name = $cities[$cityindex];
                    $destinationcityQuery = DB::table('City')->select('*')->where('name', $City_name)->first();     // fetch the destination city information from database
                    $destinationcity = get_object_vars($destinationcityQuery);  // to convert stdclass object to array
                    $distance = self::haversineDistance($sourcecity, $destinationcity);
                    $travelmethod = CustomGraph::travell_method($distance) ;
                    $places_day = Data::fetchData($preferedplaces, $Data, $City_name, $travelmethod);        //get the data by places1; places1 is a nested array which include the prefered places for USER and the airport


                    $graph = new Graph();
                    $graph1 = CustomGraph::buildGraph($places_day, $graph, $changecity, $hotelAttributes, $Data['PriceIsImportant'], $Cityday, $Data['places'], $resturantofday);      // create A custom graph which contain the Possible paths for USER:
                    $sourceNode = $graph1->getVertex(0);
                    $sourcNodeType = $sourceNode->getAttribute('name');

                    //$graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                    //$graphviz->display($graph1);

                    list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);     // execute Dijkstra Algorithm on the created graph
                    $path = end($paths);

                    $currenthotel = $graph1->getvertex($path[1]);
                    $nodeAttributes = $currenthotel->getAttributeBag();
                    $hotelAttributes = $nodeAttributes->getAttributes();

                    if($travelmethod == "plane") {
                        $ticketprice = $distance * 0.08 ;
                    }


                } else {
                    $Cityday += 1;
                    $resturantofday += 2;

                    foreach($path as $visitednodes) {
                        $visitednode = $graph1->getvertex($visitednodes);
                        $nodetype = $visitednode->getAttribute('placeType');
                        $nodename = $visitednode->getAttribute('name');
                        $visited[] = $nodename;
                    }

                    $places_day = Data::fetchData($preferedplaces, $Data, $City_name, $travelmethod);
                    $graph = new Graph();
                    $graph1 = CustomGraph::buildGraph($places_day, $graph, $changecity, $hotelAttributes, $Data['PriceIsImportant'], $Cityday, $Data['places'], $resturantofday);      // create A custom graph which contain the Possible paths for USER:

                    foreach ($graph1->getVertices() as $vertex) {
                        $vertextype = $vertex->getAttribute('placeType');
                        $vertexname = $vertex->getAttribute('name');
                        if($vertextype  == "Hotel") {
                            continue;
                        } elseif(in_array($vertexname, $visited)) {

                            $vertex->destroy();
                        } else {
                            continue;
                        }
                    }
                    //     $graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                    //    $graphviz->display($graph2);
                    $sourceNode = $graph1->getVertex(0);
                    $sourcNodeType = $sourceNode->getAttribute('name');
                    list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);
                    $path = end($paths);
                    //  return $path;
                    /*   if($i == 2) {
                           return $Data['places'][$City_name]['Resturants'][$Cityday];
                           return $path;
                       }*/
                    $currenthotel = $graph1->getvertex($path[0]);
                    $nodeAttributes = $currenthotel->getAttributeBag();
                    $hotelAttributes = $nodeAttributes->getAttributes();

                }

            }

            $cost = $ticketprice * $Data['N.people'];                     // calculate the total cost of all nodes
            foreach ($path as $node_id) {

                $node = $graph1->getvertex($node_id);
                $nodeprice = $node->getAttribute('price');
                $cost += $nodeprice;
            }

            // insert trip info to tripday table
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
            $trip_days["day_" . $i]['neededMony'] = ceil($cost * $Data['N.people']) ;
            $Totalcost += $cost * $Data['N.people'];

            // flightReservation
            if($travelmethod == "plane") {
                if($i == 1) {
                    $travelfromcity = $Data['fromcity'];
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

            foreach ($path as $nodeid) {
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

                    // insert place to dayplaces table

                    $place_type = $dayPlaces[$n]['placeType'];
                    switch($place_type) {
                        case "natural":
                            DB::table('dayplaces')->insert(
                                ['day_id' => $dayid,
                                'naturalplace_id' =>  $dayPlaces[$n]['id'],
                                'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                                'money_amount' => 0,
                                ]
                            );

                            break;

                        case "old":

                            DB::table('dayplaces')->insert(
                                ['day_id' => $dayid,
                                'oldplace_id' =>   $dayPlaces[$n]['id'],
                                'transport_method' =>  $dayPlaces[$n]['transportaionMethod'],
                                'money_amount' =>  $dayPlaces[$n]['price'] * $numberOfPeople,
                                ]
                            );

                            break;

                        case "shooping":

                            DB::table('dayplaces')->insert(
                                ['day_id' => $dayid,
                                'shoopingplace_id' =>  $dayPlaces[$n]['id'],
                                'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                                'money_amount' => 0,
                                ]
                            );

                            break;

                        case"night":
                            DB::table('dayplaces')->insert(
                                ['day_id' => $dayid,
                                'nightplace_id' =>  $dayPlaces[$n]['id'],
                                'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                                'money_amount' => $dayPlaces[$n]['price'] * $numberOfPeople,
                                ]
                            );

                            break;

                        case "Hotel":
                            if($i == 1 || $changecity == true) {
                                DB::table('dayplaces')->insert(
                                    ['day_id' => $dayid,
                                    'hotel_id' =>  $dayPlaces[$n]['id'],
                                    'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                                    'money_amount' => $dayPlaces[$n]['price'] * $numberOfPeople,
                                    ]
                                );

                            } else {
                                DB::table('dayplaces')->insert(
                                    ['day_id' => $dayid,
                                    'hotel_id' =>  $dayPlaces[$n]['id'],
                                    'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                                    'money_amount' => 0,
                                    ]
                                );
                            }

                            break;

                        case "Resturant":

                            $Resturantsofday[] = $dayPlaces[$n]['id'];

                            break;

                        case"Airport":
                            DB::table('dayplaces')->insert(
                                ['day_id' => $dayid,
                                'airport_id' =>  $dayPlaces[$n]['id'],
                                'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                                'money_amount' => ceil($ticketprice * $Data['N.people']),
                                ]
                            );

                            break;

                    }

                }
                $isFirstnode = false;
                $prenode = $graph1->getvertex($nodeid);

            }

            $trip_days["day_" . $i]['dayPlaces'] = $dayPlaces;

            $response['tripDays'] = $trip_days ;

            // insert restueant id to dayplaces table
            for ($k = 1 ; $k <= count($Resturantsofday) ;$k++) {
                if($k == 1) {
                    DB::table('dayplaces')->insert(
                        ['day_id' => $dayid,
                        'resturant1_id' =>  $dayPlaces[$n]['id'],
                        'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                        'money_amount' => 0,
                        ]
                    );
                } else {
                    DB::table('dayplaces')->insert(
                        ['day_id' => $dayid,
                        'resturant2_id' =>  $dayPlaces[$n]['id'],
                        'transport_method' => $dayPlaces[$n]['transportaionMethod'],
                        'money_amount' => 0,
                        ]
                    );
                }
            }

        }

        if($i > $city1NumberOfDays) {
            $days += 1;
        }

        $ticketprice = 0;
        $response['TotalCost'] = ceil($Totalcost) ;
        return $response;
    }

}
