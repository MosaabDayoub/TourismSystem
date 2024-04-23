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




    public static function addWeightedEdge(Vertex $vertex1, Vertex $vertex2, $price_is_important)   // A funcntion for add direct edge with weight
    {
        $distance = self::haversineDistance($vertex1, $vertex2);

        $price = $vertex2->getAttribute('price');
        if($price_is_important == true) {
            $weight = $distance + $price; // distance of destination node
        } else {
            $weight = $distance;
        }
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
        $priceisimportant,
        $dayofcity,
        $placesofuser,
        $Resturantofday
    ) {
        $Currentcityname = $places_multi['Currentcity']['name'];
        $level4exist = false;
        $level5exist = false;
        $level6exist = false;
        if (array_key_exists('Airport', $places_multi)) {
            $root = $places_multi['Airport'][0];
        } else {
            $root = $hotel;
        }

        $startnode1 = self::createNode($graph, $root);  // create start node

        // create level1 (for hotels) if start node is the airport (on the first day)

        if(array_key_exists('Airport', $places_multi) || $changecity == true) {
            $Hotelslevel1 = [];
            if(key_exists($Currentcityname, $placesofuser) && key_exists('Hotels', $placesofuser[$Currentcityname]) && !empty($placesofuser[$Currentcityname]['Hotels'])) {
                $nodelevel1 = self::createNode($graph, $placesofuser[$Currentcityname]['Hotels'][0]);
                $Hotelslevel1[] = $nodelevel1 ;

            } else {
                foreach ($places_multi['Hotels'] as $hotels => $hotel) {
                    $nodelevel1 =  self::createNode($graph, $hotel);
                    $Hotelslevel1[] = $nodelevel1;
                }
            }
            foreach($Hotelslevel1 as $hotel) {
                self::addWeightedEdge($startnode1, $hotel, $priceisimportant);
            }
        }


        // create level2 or level1 if start node isn't Airport (for resturants)
        $resturants_level1 = [];
        if(key_exists($Currentcityname, $placesofuser) && key_exists('Resturants', $placesofuser[$Currentcityname]) && key_exists($Resturantofday, $placesofuser[$Currentcityname]['Resturants'])) {
            $nodelevel2 = self::createNode($graph, $placesofuser[$Currentcityname]['Resturants'][$Resturantofday]);
            $resturants_level1[] = $nodelevel2;
            if(array_key_exists('Airport', $places_multi) || $changecity == true) {
                foreach($Hotelslevel1 as $Hotelsnode) {


                    self::addWeightedEdge($Hotelsnode, $nodelevel2, $priceisimportant);

                }

            }

            if(!array_key_exists('Airport', $places_multi) && $changecity !== true) {
                self::addWeightedEdge($startnode1, $nodelevel2, $priceisimportant);
            }

        } else {

            foreach ($places_multi['Resturants'] as $Resturants => $Resturant) {


                $nodelevel2 = self::createNode($graph, $Resturant);
                $resturants_level1[] = $nodelevel2;
                if(array_key_exists('Airport', $places_multi) || $changecity == true) {
                    foreach($Hotelslevel1 as $Hotelsnode) {


                        self::addWeightedEdge($Hotelsnode, $nodelevel2, $priceisimportant);

                    }

                }

                if(!array_key_exists('Airport', $places_multi) && $changecity !== true) {
                    self::addWeightedEdge($startnode1, $nodelevel2, $priceisimportant);
                }

            }

        }
        // create level3 (natural)
        $level3 = [];
        if(key_exists($Currentcityname, $placesofuser) && key_exists('natural', $placesofuser[$Currentcityname]) && key_exists($dayofcity, $placesofuser[$Currentcityname]['natural'])) {
            $nodelevel3 = self::createNode($graph, $placesofuser[$Currentcityname]['natural'][$dayofcity]);
            $level3[] = $nodelevel3 ;
            foreach($resturants_level1 as $resturantnode1) {

                self::addWeightedEdge($resturantnode1, $nodelevel3, $priceisimportant);

            }

        } else {

            if (key_exists('natural', $places_multi)) {

                foreach ($places_multi['natural'] as $naturalplaces2 => $places) {

                    $nodelevel3 = self::createNode($graph, $places);
                    $level3[] = $nodelevel3 ;



                    foreach($resturants_level1 as $resturantnode1) {

                        self::addWeightedEdge($resturantnode1, $nodelevel3, $priceisimportant);

                    }
                }

            }
        }

        // create level4 (old)
        $level4 = [];
        if(key_exists($Currentcityname, $placesofuser) && key_exists('old', $placesofuser[$Currentcityname]) && key_exists($dayofcity, $placesofuser[$Currentcityname]['old'])) {
            $nodelevel4 = self::createNode($graph, $placesofuser[$Currentcityname]['old'][$dayofcity]);
            $level4[] = $nodelevel4 ;
            $level4exist = true;

        } else {
            if (key_exists('old', $places_multi)) {
                foreach ($places_multi['old'] as $oldplaces2 => $oldplace2) {
                    $nodeslevel4 = self::createNode($graph, $oldplace2);
                    $level4[] = $nodeslevel4;
                }
            }
        }
        if (key_exists('old', $places_multi) || $level4exist == true) {
            foreach ($level4 as $nodelevel4) {


                if(key_exists('natural', $places_multi)) {
                    foreach($level3 as $placenode) {

                        self::addWeightedEdge($placenode, $nodelevel4, $priceisimportant);


                    }
                } else {
                    foreach($resturants_level1 as $placenode) {

                        self::addWeightedEdge($placenode, $nodelevel4, $priceisimportant);


                    }
                }

            }
        }


        // create level5 (shooping)
        $level5 = [];
        if(key_exists($Currentcityname, $placesofuser) && key_exists('shopping', $placesofuser[$Currentcityname]) && key_exists($dayofcity, $placesofuser[$Currentcityname]['shopping'])) {
            $nodelevel5 = self::createNode($graph, $placesofuser[$Currentcityname]['shopping'][$dayofcity]);
            $level5[] = $nodelevel5 ;
            $level5exist = true;

        } else {

            if (key_exists('shopping', $places_multi)) {
                foreach ($places_multi['shopping'] as $shoopingplaces) {
                    $nodeslevel5 = self::createNode($graph, $shoopingplaces);
                    $level5[] = $nodeslevel5;
                }
            }

        }
        if(key_exists('shopping', $places_multi) || $level5exist == true) {

            foreach ($level5 as $place5) {

                if(key_exists('old', $places_multi)) {
                    foreach($level4 as $placenode) {

                        self::addWeightedEdge($placenode, $place5, $priceisimportant);


                    }
                } elseif(key_exists('natural', $places_multi)) {
                    foreach($level3 as $placenode) {

                        self::addWeightedEdge($placenode, $place5, $priceisimportant);


                    }
                } else {
                    foreach($resturants_level1 as $placenode) {

                        self::addWeightedEdge($placenode, $place5, $priceisimportant);

                    }
                }
            }

        }

        //create level6 nightplace
        $level6 = [];
        if(key_exists($Currentcityname, $placesofuser) && key_exists('night', $placesofuser[$Currentcityname]) && key_exists($dayofcity, $placesofuser[$Currentcityname]['night'])) {
            $nodelevel6 = self::createNode($graph, $placesofuser[$Currentcityname]['night'][$dayofcity]);
            $level6[] = $nodelevel6 ;
            $level6exist = true;

        } else {

            if (key_exists('night', $places_multi)) {
                foreach ($places_multi['night'] as $nightplaces) {
                    $nodeslevel6 = self::createNode($graph, $nightplaces);
                    $level6[] = $nodeslevel6;
                }
            }

        }

        if (key_exists('night', $places_multi) || $level6exist == true) {
            foreach ($level6 as $place6) {

                if(key_exists('shopping', $places_multi)) {
                    foreach($level5 as $placenode) {

                        self::addWeightedEdge($placenode, $place6, $priceisimportant);


                    }
                } elseif(key_exists('old', $places_multi)) {
                    foreach($level4 as $placenode) {

                        self::addWeightedEdge($placenode, $place6, $priceisimportant);


                    }
                } elseif(key_exists('natural', $places_multi)) {
                    foreach($level3 as $placenode) {

                        self::addWeightedEdge($placenode, $place6, $priceisimportant);

                    }
                } else {
                    foreach($resturants_level1 as $placenode) {

                        self::addWeightedEdge($placenode, $place6, $priceisimportant);

                    }
                }
            }
        }

        // create level7 (for resturants)
        $resturants_level2 = [];
        if(key_exists($Currentcityname, $placesofuser) && key_exists('Resturants', $placesofuser[$Currentcityname]) && key_exists($Resturantofday + 1, $placesofuser[$Currentcityname]['Resturants'])) {
            $nodelevel7 = self::createNode($graph, $placesofuser[$Currentcityname]['Resturants'][$Resturantofday + 1]);
            $resturants_level2[] = $nodelevel7 ;
            $level7exist = true;

        } else {

            foreach ($places_multi['Resturants'] as $Resturants2) {
                $nodelevel7 = self::createNode($graph, $Resturants2);
                $resturants_level2[] = $nodelevel7;
            }
        }

        foreach ($resturants_level2 as $Resturant2) {

            if (key_exists('night', $places_multi)) {
                foreach($level6 as $nightnode) {

                    self::addWeightedEdge($nightnode, $Resturant2, $priceisimportant);

                }

            } elseif(key_exists('shopping', $places_multi)) {
                foreach($level5 as $placenode) {

                    self::addWeightedEdge($placenode, $Resturant2, $priceisimportant);


                }
            } elseif(key_exists('old', $places_multi)) {
                foreach($level4 as $placenode) {

                    self::addWeightedEdge($placenode, $Resturant2, $priceisimportant);


                }
            } elseif(key_exists('natural', $places_multi)) {
                foreach($level3 as $placenode) {

                    self::addWeightedEdge($placenode, $Resturant2, $priceisimportant);

                }
            } else {
                foreach($resturants_level1 as $placenode) {

                    self::addWeightedEdge($placenode, $Resturant2, $priceisimportant);

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

            self::addWeightedEdge($resturants3, $endnode, $priceisimportant);

        }

        return $graph;

    }

}
