<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
//use App\Models\Dayplace;
//use App\Models\FlightReservation;
use App\Models\Hotel;
//use App\Models\HotelReservation;
//use App\Models\Trip;
use App\Models\naturalplace;
use App\Models\Nightplace;
//use App\Models\User;
use App\Models\ShoopingPlace;
use App\Models\Oldplace;
use App\Models\Resturant;

use App\Services\GenerateTrip\CustomGraph;
use App\Services\GenerateTrip\Data;
//use App\Services\GenerateTrip\DijkstraAlgorithm ;

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

        //$locTo = CustomGraph::getlocation($destination['location']);
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
        $fromCity = $Data['fromcity'];
        $toCountry = $Data['tocountry'];
        $numberOfDays = $Data['N.days'];
        $numberOfPeople = $Data['N.people'];
        $preferedplaces = $Data['preferedplaces'];
        $preferedfood = $Data['preferedfood'];
        $PriceIsImportant = $Data['PriceIsImportant'];

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

        $response = [];       // response Array Initialization

        for ($i = 1 ; $i <= $numberOfDays ;$i++) {         // loop for every day in the trip
            $changecity = false;

            if ($i == 1) {        // Day1
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
                $graph1 = CustomGraph::buildGraph($places_day, $graph, false, null, $Data['PriceIsImportant']);      // create A custom graph which contain the Possible paths for USER:
                $sourceNode = $graph1->getVertex(0);

                //$graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                //$graphviz->display($graph1);

                list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);     // execute Dijkstra Algorithm on the created graph
                $path = end($paths);

                $currenthotel = $graph1->getvertex($path[1]);
                $nodeAttributes = $currenthotel->getAttributeBag();
                $hotelAttributes = $nodeAttributes->getAttributes();


            } else { //other days
                $date = date("Y-m-d", strtotime($Data['date'] . ' +1 day'));

                $travelmethod = null;


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
                    $places_day = Data::fetchData($preferedplaces, $Data, $City_name, $travelmethod);        //get the data by places1; places1 is a nested array which include the prefered places for USER and the airport


                    $graph = new Graph();
                    $graph1 = CustomGraph::buildGraph($places_day, $graph, $changecity, $hotelAttributes, $Data['PriceIsImportant']);      // create A custom graph which contain the Possible paths for USER:
                    $sourceNode = $graph1->getVertex(0);
                    $sourcNodeType = $sourceNode->getAttribute('name');

                    //$graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                    //$graphviz->display($graph1);

                    list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);     // execute Dijkstra Algorithm on the created graph
                    $path = end($paths);

                    $currenthotel = $graph1->getvertex($path[1]);

                    if($travelmethod == "plane") {
                        $ticketprice = $distance * 0.08 ;
                    }


                } else {

                    list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $currenthotel);
                    $path = end($paths);
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

            $response['date'] = date("Y-m-d", strtotime($Data['date']));
            $response['fromCity'] = $Data['fromcity'];
            $response['destination'] = $Data['tocountry'];
            $response['totalBudget'] = $Data['totalBudget'];
            $response['numberOfPeople'] = $Data['N.people'];

            //$trip_days = ["day_" . $i];
            $trip_days["day_" . $i]['dayId'] = $i;
            $trip_days["day_" . $i]['date'] = $date;
            $trip_days["day_" . $i]['city'] = $places_day['Currentcity'];
            $trip_days["day_" . $i]['neededMony'] = ceil($cost * $Data['N.people']) ;
            $Totalcost += $cost * $Data['N.people'];
            // flightReservation
            if($travelmethod == "plane") {
                $response['flightReservation']["day_" . $i] = [ "airportId" => $places_day['Airport'][0]['id'] ,"fromCity" => $Data['fromcity'] ,
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

                    }
                }
                $isFirstnode = false;
                $prenode = $graph1->getvertex($nodeid);

            }

            $trip_days["day_" . $i]['dayPlaces'] = $dayPlaces;

            $response['tripDays'] = $trip_days ;

            foreach($path as $visitednodes) {
                $visitednode = $graph1->getvertex($visitednodes);
                $nodetype = $visitednode->getAttribute('placeType');
                if($nodetype = "Hotel" || $nodetype = "Airport") {
                    continue;


                } else {
                    $visitednode->destroy();
                }
                if($i > $city1NumberOfDays) {
                    $days += 1;
                }

            }

        }
        $ticketprice = 0;
        $response['TotalCost'] = ceil($Totalcost) ;
        return $response;
    }

}
