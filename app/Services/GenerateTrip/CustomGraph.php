<?php

namespace App\Services\GenerateTrip;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

use Fhaculty\Graph\Graph ;
use Fhaculty\GraphVertex;
use Fhaculty\Graph\Vertex;
use Fhaculty\Graph\Edge\Base;
use Fhaculty\Graph\Edge\Directed ;
use Fhaculty\Graph\Set\Vertices;

class CustomGraph extends Graph
{
    public static function travell_method($distance1)      // A function for find transportaionMethod
    {
        if  ($distance1 <= 3000) {

            return $transportaionMethod = "walking";

        } elseif($distance1 > 1 && $distance1 <= 350000) {

            return $transportaionMethod = "car";

        } else {

            return $transportaionMethod = "plane";
        }
    }

    public static function createNode(Graph $graph1, array $attributes)      // A function to create node with parameter:array of attribute
    {
        $node = $graph1->createVertex();

        foreach ($attributes as $key => $value) {
            $node->setAttribute($key, $value);
        }

        return $node;
    }
    // A function for get loacation as lat and lon from location string
    public static function getlocation($loc)
    {
        $spacePosition = strpos($loc, ' ');       // search in string (location) about space position

        $lat = substr($loc, 0, $spacePosition);     // cut the string (location) from beginning to space position

        $lon = substr($loc, $spacePosition + 1);     //cut the string (location) from space position to the end

        $location = ['latitude' => floatval($lat),'longitude' => floatval($lon)];   // how the array will look like
        return $location;
    }


    public static function haversineDistance(Vertex $vertex1, Vertex $vertex2)      // A function for calculate distance between two node
    {
        $locFrom_string = $vertex1->getAttribute('location'); // get the location string from vertex
        $locFrom_array = self::getlocation($locFrom_string); // format the location string for get the lat and lon
        $locTo_string = $vertex2->getAttribute('location');
        $locTo_array = self::getlocation($locTo_string);

        $latFrom = deg2rad($locFrom_array['latitude']);
        $lonFrom = deg2rad($locFrom_array['longitude']);
        $latTo = deg2rad($locTo_array['latitude']);
        $lonTo = deg2rad($locTo_array['longitude']);

        // haversineDistance
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        $distance = $angle *  6371000; // 6371000 m : نصف قطر الارض

        return $distance;
    }




    public static function addWeightedEdge(Vertex $vertex1, Vertex $vertex2)   // A funcntion for add direct edge with weight
    {
        $distance = self::haversineDistance($vertex1, $vertex2);
        $weight = $distance;
        $edge = new Directed($vertex1, $vertex2);
        $edge->setWeight($weight);
        $edge->setAttribute('distance', $distance);
        $Method = self::travell_method($distance);
        $edge->setAttribute('travelMethod', $Method);

    }

    // A function for create A custom Graph
    public static function buildGraph(
        $places_multi,
        Graph $graph,
        $changecity,
        $hotel,
        $PreferedPlacesselected,
        $travelmethod,
        $end
    ) {

        $i = 0;
        $unshift_hotel = false;
        $resturants_level1 = [];
        $PreferedPlacesselected[] = "Resturants2";
        ${"level" . $i} = [];

        if ($travelmethod == "plane") {
            $root = $places_multi['Airport'][0];

        } else {
            $root = $hotel;
            $unshift_hotel = true;
        }

        $startnode1 = self::createNode($graph, $root);  // create start node
        ${"level" . $i}[] = $startnode1;

        array_unshift($PreferedPlacesselected, "Resturants1");
        if($travelmethod == "plane" || $changecity == true) {
            array_unshift($PreferedPlacesselected, "Hotels");
        }
        //create levels
        foreach($PreferedPlacesselected as $PreferedPlaceselected) {
            $i += 1;
            ${"level" . $i} = [];

            foreach ($places_multi[$PreferedPlaceselected] as $places) {

                ${"nodelevel" . $i} = self::createNode($graph, $places);
                ${"level" . $i}[] = ${"nodelevel" . $i} ;


                foreach(${"level" . ($i - 1)} as $prelevelnode) {

                    self::addWeightedEdge($prelevelnode, ${"nodelevel" . $i});


                }

            }
        }
        //create fake node (last level)

        $endnode = self::createNode($graph, $end);

        foreach(${"level" . $i} as $resturants2) {

            self::addWeightedEdge($resturants2, $endnode);

        }

        return $graph;

    }
}
