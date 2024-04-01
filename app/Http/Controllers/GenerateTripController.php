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
//use Fhaculty\Graph\Set\Vertices ;
use Graphp\GraphViz\GraphViz;
use App\Services\GenerateTrip\DijkstraAlgorithm;



class GenerateTripController extends Controller
{
    public static function haversineDistance($source,$destination){      // A function for calculate a haversineDistance between two point

        $latfrom = $source['latitude'];
        $latTo = $destination[0]['lat'];
        $lonfrom = $source['longitude'];
        $lonTo = $destination[0]['lon'];
        

        $latDelta = $latTo - $latfrom;
        $lonDelta = $lonTo - $lonfrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latfrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        $distance= $angle *  6371;
        return $distance;
    }

    
        
        function generate(Request $request){       // A function for generate the trip 

        $Data=$request->all(); 
        $fromCity = $Data['fromcity'];
        $toCountry = $Data['tocountry'];
        $numberOfDays = $Data['N.days'];
        $numberOfPeople = $Data['N.people'];
        $preferedplaces = $Data['preferedplaces'];
        $preferedfood = $Data['preferedfood'];
        
        
            

            // fetch cities of the desination country
            $countrycitiesQuery = DB::table('City')
            ->join('country', 'City.country', '=', 'country.country_name')
            ->select('City.name as name',"city.capital")
            ->where('country.country_name', '=', $Data['tocountry'])
            ->get()->toArray();
        
            $countrycities1['name'] = array_column($countrycitiesQuery, 'name');          // array include the city 
            $countrycities2['capital'] = array_column($countrycitiesQuery,'capital');       // array include if the city is a capital 

            
            $countrycities=array_combine( $countrycities1['name'],$countrycities2['capital']);    // array include cities of the desination country as a key and if this city is a capital as value
            $N_cities=count( $countrycities1['name']);    // number of country cities
            $cityNumberOfDays = floor($N_cities / $numberOfDays);           // number of day in each city in the country
             
             
            $response=[];       // response Array Initialization

            //for ($i=1 ; $i <= $numberOfDays ;$i++){         // loop for every day in the trip 

                //if ($i == 1) {        // Day1

                    
                        $city_name = array_search(1, $countrycities);            // find the capital
                        unset($countrycities[$city_name]);
                        
                        $places_day1=Data::fetchData($preferedplaces,$Data,$city_name,true);        //get the data by places1; places1 is a nested array which include the prefered places for USER and the airport
                        
                        $distance_airport = self::haversineDistance($places_day1['Currentcity'],$places_day1['Airport']);   // calculate ticket price for tavel to destination city
                        $ticketprice = $distance_airport * 0.08 ;

                        $travelmethod = CustomGraph::travell_method($distance_airport);   // find the travel method



                        $graph = new Graph();
                        $graph1 = CustomGraph::buildGraph($places_day1,$places_day1['Airport'],$graph,false);      // craete A custom graph which contain the Possible paths for USER:
                        $sourceNode = $graph1->getVertex(0);

                        $graphviz = new GraphViz(['binary' => 'C:\Program Files\Graphviz\bin\dot.exe']);      // for display the created graph
                        $graphviz->display($graph1);
      
                        list($distances, $previous, $paths) = DijkstraAlgorithm::allShortestPaths($graph1, $sourceNode);     // execute Dijkstra Algorithm on the created graph
                        
                        $path = end($paths); 
                        return $path;     // the shortest path

                        $cost=0;                     // calculate the total cost of all nodes
                        foreach ($path as $node_id) {

                            $node=$graph1->getvertex($node_id);
                            $nodeprice=$node->getAttribute('price');
                            $cost+=$nodeprice;
                        }
                        
                        
                        $response['date'] = $Data['date'];
                        $response['fromCity'] = $Data['fromcity'];
                        $response['destination'] = $Data['tocountry'];
                        $response['totalBudget'] = $Data['totalBudget'];
                        $response['numberOfPeople'] = $Data['N.people'];
                        
                        $trip_day = [];
                        //$trip_day['dayId'] = $i;
                        $trip_day['date'] = $Data['date'];
                        $trip_day['city'] = $places_day1['Currentcity'];
                        $trip_day['neededMony'] = $cost * $Data['N.people'] ;
                        

                        // flightReservation
                        $Airport_location = $places_day1['Airport'][0]['lat']." ".$places_day1['Airport'][0]['lat'];
                        $trip_day['flightReservation'] = [ "airportId" => $places_day1['Airport'][0]['id'] ,"fromCity" => $Data['fromcity'] ,
                        "airportName" => $places_day1['Airport'][0]['name'],"address" => $places_day1['Airport'][0]['Address'],
                        "price" => $ticketprice,"toatlAmountOfMony " => $ticketprice * $Data['N.people'] ,"location" => $Airport_location 
                          ];
                        // "hotelReservation"
                        $hotel_info_key = ["id","name","price","lat","lon","Address"];
                        
                        $hotelnode = $graph1-> getvertex($path[1]);    // get hotel node by id
                        
                        
                        $hotel_info_value=[];
                        foreach ($hotel_info_key as  $key ) {

                            $hotel_info[$key]= $hotelnode ->getAttribute($key);      // get the hotel node attribute  
                        }
                        
                        $hotel_location = $hotel_info['lat'] . " " . $hotel_info['lat'];                        
                        $trip_day['hotelReservation'] = [ "hotelId " => $hotel_info['id'] ,"hotelname " =>  $hotel_info['name'] ,
                        "Address" => $hotel_info['Address'],"price" => $hotel_info['price'],
                        "totatlAmountOfMony" => $hotel_info['price'] * $Data['N.people'],"location " => $hotel_location ,
                          ];
                        return $trip_day;

                        // places of day:  
                        $dayPlaces=[];
                        $place_info_key=["id","name","placeType","address","placeType","price",];
                        $n = -1;
                        
                            $f=count($path);
                            $n += 1;
                            $m=0;
                            
                            
                            for($i=0;$i<= $f ;$i++){     
                                
                                foreach ($place_info_key as $key) {
                                    $node=$graph1->getvertex($nodeid);
                                    $dayPlaces[$n][$key] = $node->getAttribute($key);
                                    $place_lat = $node->getAttribute('lat');
                                    $place_lon = $node->getAttribute('lon');
                                    $dayPlaces[$n]['location'] = $place_lat." ".$place_lon;
                                    //$edge_in = $node->getEdgesIn();
                                    //$dayPlaces[$n]['transportaionMethod'] = $edge_in->getAttribute('Method');
                                    
                                    //if ($dayPlaces[$n]['placeType'] == 'hotel' || $dayPlaces[$n]['placeType'] == 'resturant') {

                                    //    $dayPlaces[$n]['stars'] = $node->getAttribute('stars');
                                }
                        }
                            
                        
                        $trip_day['$dayPlaces'] = $dayPlaces;
                        return $dayPlaces;



                        $response['$trip_day'] = $trip_day ;
                        return $response;
                        

                          



                        


                          

                        


                    //}










         
            
            
            
            


            
            















                    }   


        }

    













