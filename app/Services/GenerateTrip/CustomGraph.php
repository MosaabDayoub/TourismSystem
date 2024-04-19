<?php

namespace App\Services\GenerateTrip;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Fhaculty\Graph\Graph ;
use Fhaculty\GraphVertex;
use Fhaculty\Graph\Vertex;
use Fhaculty\Graph\Edge\Base;
use Fhaculty\Graph\Edge\Directed ;


use Graphp\Algorithms\ShortestPath\Dijkstra;
use App\Services\GenerateTrip\DijkstraAlgorithm;
use Fhaculty\Graph\Set\Vertices;
use App\Http\Controllers\GenerateTripController;

class CustomGraph extends Graph
{
    public static function travell_method($distance1)      // A function for find transportaionMethod
    {
        if  ($distance1 <= 1) {

            return $transportaionMethod = "walking";

        } elseif($distance1 > 1 && $distance1 <= 30) {

            return $transportaionMethod = "car";

        } elseif($distance1 > 30 && $distance1 < 100) {

            return $transportaionMethod = "train";

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

        $location = ['lat' => floatval($lat),'lon' => floatval($lon)];   // how the array will look like
        return $location;
    }


    public static function haversineDistance(Vertex $vertex1, Vertex $vertex2)      // A function for calculate distance between two node
    {
        $locFrom_string = $vertex1->getAttribute('location'); // get the location string from vertex
        $locFrom_array = self::getlocation($locFrom_string); // format the location string for get the lat and lon
        $locTo_string = $vertex2->getAttribute('location');
        $locTo_array = self::getlocation($locTo_string);

        $latFrom = deg2rad($locFrom_array['lat']);
        $lonFrom = deg2rad($locFrom_array['lon']);
        $latTo = deg2rad($locTo_array['lat']);
        $lonTo = deg2rad($locTo_array['lon']);

        // haversineDistance
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        $distance = $angle *  6371; // 6371 km : نصف قطر الارض

        return $distance;
    }




    public static function addWeightedEdge(Vertex $vertex1, Vertex $vertex2)   // A funcntion for add direct edge with weight
    {
        $distance = self::haversineDistance($vertex1, $vertex2);

        $price = $vertex2->getAttribute('price');

        $weight = $distance + $price; // distance of destination node

        $edge = new Directed($vertex1, $vertex2);

        $edge->setWeight($weight);
        $edge->setAttribute('distance', $distance);
        $Method = self::travell_method($distance);
        $edge->setAttribute('travelMethod', $Method);



    }


    public static function buildGraph(// A function for create A custom Graph
        $places_multi,
        Graph $graph,
        $changecity,
        $hotel,
    ) {
        if (array_key_exists('Airport', $places_multi)) {
            $root = $places_multi['Airport'][0];
        } else {
            $root = $hotel;
        }

        $startnode1 = self::createNode($graph, $root);  // create start node

        // create level1 (for hotels) if start node is the airport (on the first day)
        if(array_key_exists('Airport', $places_multi) || $changecity == true) {
            $Hotelslevel1 = [];
            foreach ($places_multi['Hotels'] as $hotels => $hotel) {


                $nodelevel1 =  self::createNode($graph, $hotel);
                $Hotelslevel1[] = $nodelevel1;
                self::addWeightedEdge($startnode1, $nodelevel1);



            }
        }


        // create level2 or level1 if start node isn't Airport (for resturants)
        $resturants_level1 = [];
        foreach ($places_multi['Resturants'] as $Resturants => $Resturant) {


            $nodelevel2 = self::createNode($graph, $Resturant);
            $resturants_level1[] = $nodelevel2;
            if(array_key_exists('Airport', $places_multi) || $changecity == true) {
                foreach($Hotelslevel1 as $Hotelsnode) {


                    self::addWeightedEdge($Hotelsnode, $nodelevel2);

                }

            }


            if(!array_key_exists('Airport', $places_multi) && $changecity !== true) {
                self::addWeightedEdge($startnode1, $nodelevel2);
            }

        }


        // create level3 (natural or shopping) or (old)
        $level3 = [];

        if (key_exists('natural', $places_multi) && key_exists('shopping', $places_multi)) {
            $placesA = array_merge($places_multi['natural'], $places_multi['shopping']);

            foreach ($placesA as $places) {

                $nodelevel3 = self::createNode($graph, $places);
                $level3[] = $nodelevel3 ;

                foreach($resturants_level1 as $resturantnode1) {

                    self::addWeightedEdge($resturantnode1, $nodelevel3);

                }
            }

        } elseif(key_exists('natural', $places_multi)) {
            foreach ($places_multi['natural'] as $places) {

                $nodelevel3 = self::createNode($graph, $places);
                $level3[] = $nodelevel3 ;

                foreach($resturants_level1 as $resturantnode1) {

                    self::addWeightedEdge($resturantnode1, $nodelevel3);

                }
            }
        } elseif(key_exists('shopping', $places_multi)) {
            foreach ($places_multi['shopping'] as $places) {

                $nodelevel3 = self::createNode($graph, $places);
                $level3[] = $nodelevel3 ;

                foreach($resturants_level1 as $resturantnode1) {

                    self::addWeightedEdge($resturantnode1, $nodelevel3);

                }
            }
        } else {
            foreach ($places_multi['old'] as $oldplaces => $oldplace) {

                $nodelevel3 = self::createNode($graph, $oldplace);
                $level3[] = $nodelevel3;

                foreach($resturants_level1 as $resturantnode1) {

                    self::addWeightedEdge($resturantnode1, $nodelevel3);


                }
            }
        }
        // create level4 (old) or  (natural or shopping)
        $level4 = [];
        if (key_exists('old', $places_multi)) {
            foreach ($places_multi['old'] as $oldplaces2 => $oldplace2) {

                $nodelevel4 = self::createNode($graph, $oldplace2);
                $level4[] = $nodelevel4;

                foreach($level3 as $placenode4) {

                    self::addWeightedEdge($placenode4, $nodelevel4);


                }
            }
        } elseif(key_exists('natural', $places_multi) && key_exists('shopping', $places_multi)) {
            $placesB = array_merge($places_multi['natural'], $places_multi['shopping']);

            foreach ($placesB as $places2) {

                $nodelevel4 = self::createNode($graph, $places2);
                $level4[] = $nodelevel4 ;

                foreach($level3 as $placenodeA) {

                    self::addWeightedEdge($placenodeA, $nodelevel4);

                }
            }


        } elseif(key_exists('shopping', $places_multi)) {
            foreach ($places_multi['shopping'] as $places2) {

                $nodelevel4 = self::createNode($graph, $places2);
                $level4[] = $nodelevel4 ;

                foreach($level3 as $placenodeA) {

                    self::addWeightedEdge($placenodeA, $nodelevel4);
                }
            }
        } elseif(key_exists('natural', $places_multi)) {
            foreach ($places_multi['natural'] as $places2) {

                $nodelevel4 = self::createNode($graph, $places2);
                $level4[] = $nodelevel4 ;

                foreach($level3 as $placenodeA) {

                    self::addWeightedEdge($placenodeA, $nodelevel4);
                }
            }
        }


        // create level5 (natural or shopping) or (old)
        $level5 = [];

        if (key_exists('natural', $places_multi) && key_exists('shopping', $places_multi)) {
            $placesC = array_merge($places_multi['natural'], $places_multi['shopping']);

            foreach ($placesC as $places3) {

                $nodelevel5 = self::createNode($graph, $places3);
                $level5[] = $nodelevel5 ;

                foreach($level4 as $placeBnode) {

                    self::addWeightedEdge($placeBnode, $nodelevel5);

                }
            }

        } elseif(key_exists('natural', $places_multi)) {
            foreach ($places_multi['natural'] as $places3) {

                $nodelevel5 = self::createNode($graph, $places3);
                $level5[] = $nodelevel5 ;

                foreach($level4 as $placeBnode) {

                    self::addWeightedEdge($placeBnode, $nodelevel5);

                }
            }

        } elseif(key_exists('shopping', $places_multi)) {
            foreach ($places_multi['shopping'] as $places3) {

                $nodelevel5 = self::createNode($graph, $places3);
                $level5[] = $nodelevel5 ;

                foreach($level4 as $placeBnode) {

                    self::addWeightedEdge($placeBnode, $nodelevel5);

                }
            }

        } else {
            foreach ($places_multi['old'] as $oldplaces3 => $oldplace3) {

                $nodelevel5 = self::createNode($graph, $oldplace3);
                $level5[] = $nodelevel5;

                foreach($level4 as $placeBnode) {

                    self::addWeightedEdge($placeBnode, $nodelevel5);


                }
            }

        }
        //create level6 nightplace
        if (key_exists('night', $places_multi)) {
            $level6 = [];
            foreach ($places_multi['night'] as $nightplaces => $nightplace) {

                $nodelevel6 = self::createNode($graph, $nightplace);
                $level6[] = $nodelevel6;

                foreach($level5 as $placeCnode) {

                    self::addWeightedEdge($placeCnode, $nodelevel6);


                }
            }
        }

        // create level7 (for resturants)
        $resturants_level2 = [];
        foreach ($places_multi['Resturants'] as $Resturants2 => $Resturant2) {


            $nodelevel7 = self::createNode($graph, $Resturant2);
            $resturants_level2[] = $nodelevel7;
            if (key_exists('night', $places_multi)) {
                foreach($level6 as $nightnode) {

                    self::addWeightedEdge($nightnode, $nodelevel7);

                }

            } else {
                foreach($level5 as $level5node) {

                    self::addWeightedEdge($level5node, $nodelevel7);
                }
            }
        }

        //create level8 (fakenode)

        $end = ["id" => "10","name" => "destination",
        "lon" => 11.251828422382225,
        "lat" => 40.803499350173176,
        "price" => 0,
        "placeType" => null,
        ];
        $endnode = self::createNode($graph, $end);

        foreach($resturants_level2 as $resturants3) {

            self::addWeightedEdge($resturants3, $endnode);

        }

        return $graph;

    }

}
