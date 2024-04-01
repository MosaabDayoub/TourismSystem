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


class CustomGraph extends Graph{

 

    public static function travell_method($distance1){      // A function for find transportaionMethod

    if  ($distance1 <= 1) {

    $transportaionMethod="walking";

    }elseif($distance1 > 1 && $distance1 <= 30 ) {

    $transportaionMethod="car";

    }elseif($distance1 > 30 && $distance1 < 100){

    $transportaionMethod= "train";

    }else{

    $transportaionMethod= "plane";
    }
    }

    public static function createNode(Graph $graph1,array $attributes) {      // A function to create node with parameter:array of attribute
        
    $node = $graph1->createVertex();

    foreach ($attributes as $key => $value) {
    $node->setAttribute($key, $value);
    }

    return $node;
    }




    public static function haversineDistance(Vertex $vertex1, Vertex $vertex2) {      // A function for calculate distance between two node 

    $latFrom = deg2rad($vertex1->getAttribute('lat'));
    $lonFrom = deg2rad($vertex1->getAttribute('lon'));
    $latTo = deg2rad($vertex2->getAttribute('lat'));
    $lonTo = deg2rad($vertex2->getAttribute('lon'));

    // haversineDistance   
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

    $distance= $angle *  6371; // 6371 km : نصف قطر الارض

    return $distance;
    }




    public static function addWeightedEdge(Vertex $vertex1, Vertex $vertex2) {   // A funcntion for add direct edge with weight

    $distance = self::haversineDistance($vertex1, $vertex2);

    $price = $vertex2->getAttribute('price');

    $weight = $distance + $price; // distance of destination node

    $edge = new Directed($vertex1, $vertex2);

    $edge->setWeight($weight);
    $edge->setAttribute('distance',$distance);
    $Method = self::travell_method($distance);
    $edge->setAttribute('transportaionMethod',$Method);
    


    }


public static function buildGraph(             // A function for create A custom Graph 
    $places_multi=[],
    $root=[],
    Graph $graph,
    $changecity, 
){
    
    
    
    $startnode1 = self::createNode($graph,$root);  // create start node



    // create level1 (for hotels) if start node is the airport (on the firs day)
    if($root[0]['placeType'] == "Airport" || $changecity == true) {
        $Hotelslevel1=[];
        foreach ($places_multi['Hotels'] as $hotels => $hotel) {
            
            
            $nodelevel1 =  self::createNode($graph,["id" => $hotel['id'],"name" => $hotel['name'],"lon" => $hotel['lon'],
            "lat" => $hotel['lat'],"price" => $hotel['price'],"placeType" => $hotel['placeType']]);
            $Hotelslevel1[] = $nodelevel1;
            self::addWeightedEdge($startnode1,$nodelevel1);
        
        
    
        } 
}
    
        
    // create level2 or level1 if start node isn't Airport (for resturants)
    $resturants_level1= []; 
    foreach ($places_multi['Resturants'] as $Resturants => $Resturant) {
        
    
        $nodelevel2 = self::createNode($graph,["id" => $Resturant['id'],"name" => $Resturant['name'],"lon" => $Resturant['lon'],
        "lat" => $Resturant['lat'],"price" => $Resturant['price'],"placeType" => $Resturant['placeType']]);
        $resturants_level1[] = $nodelevel2;
    
        foreach($Hotelslevel1 as $Hotelsnode)
        {
            if($root[0]['placeType'] == "Airport" || $changecity == true) {

                self::addWeightedEdge($Hotelsnode,$nodelevel2);
 
            }

        } 


        if($root[0]['placeType'] !== "Airport" && $changecity !== true) {
        self::addWeightedEdge($startnode1,$nodelevel2);
        }
        
    }
    
    
    // create level3 (natural or shopping) or (old)
    $level3= [];
    
    if (key_exists('natural', $places_multi) && key_exists('shopping', $places_multi)) {
        $placesA = array_merge( $places_multi['natural'], $places_multi['shopping']);
    
        foreach ($placesA as $places ) {
        
            $nodelevel3 = self::createNode($graph,["id" => $places['id'],"name" => $places['name'],"lon" =>  $places['lon'],
            "lat" => $places['lat'],"price" => $places['price'],"placeType" => $places['placeType']]);
            $level3[] = $nodelevel3 ;
    
            foreach($resturants_level1 as $resturantnode1){
    
                self::addWeightedEdge($resturantnode1,$nodelevel3);
                
        }
    }
    
    }
    
    
    
    foreach ($places_multi['old'] as $oldplaces => $oldplace) {
        
        $nodelevel3 = self::createNode($graph,["id" => $oldplace['id'],"name" => $oldplace['name'],"lon" => $oldplace['lon'],
        "lat" => $oldplace['lat'],"price" => $oldplace['price'],"placeType" => $oldplace['placeType']]);
        $level3[] = $nodelevel3;
        
        foreach($resturants_level1 as $resturantnode1){
    
            self::addWeightedEdge($resturantnode1,$nodelevel3);
        
    
        }
    }

    // create level4 (old) or  (natural or shopping)
    $level4=[]; 
    if (key_exists('old', $places_multi)) {
    foreach ($places_multi['old'] as $oldplaces2 => $oldplace2) {
        
        $nodelevel4 = self::createNode($graph,["id" => $oldplace2['id'],"name" => $oldplace2['name'],"lon" => $oldplace2['lon'],
        "lat" => $oldplace2['lat'],"price" => $oldplace2['price'],"placeType" => $oldplace2['placeType']]);
        $level4[] = $nodelevel4;
        
        foreach($level3 as $placenode4){
    
            self::addWeightedEdge($placenode4,$nodelevel4);
        
    
        }
    }


    $placesB = array_merge( $places_multi['natural'], $places_multi['shopping']);
    
    foreach ($placesB as $places2 ) {
    
        $nodelevel4 = self::createNode($graph,["id" => $places2['id'],"name" => $places2['name'],"lon" =>  $places2['lon'],
        "lat" => $places2['lat'],"price" => $places2['price'],"placeType" => $places2['placeType']]);
        $level4[] = $nodelevel4 ;

        foreach($level3 as $placenodeA){

            self::addWeightedEdge($placenode4,$nodelevel4);
            
    }
}

}



// create level5 (natural or shopping) or (old)
$level5= [];
    
if (key_exists('natural', $places_multi)&&key_exists('shopping', $places_multi)) {
    $placesC = array_merge( $places_multi['natural'], $places_multi['shopping']);

    foreach ($placesC as $places3 ) {
    
        $nodelevel5 = self::createNode($graph,["id" => $places3['id'],"name" => $places3['name'],"lon" =>  $places3['lon'],
        "lat" => $places3['lat'],"price" => $places3['price'],"placeType" => $places3['placeType']]);
        $level5[] = $nodelevel5 ;

        foreach($level4 as $placeBnode){

            self::addWeightedEdge($placeBnode,$nodelevel5);
            
    }
}

}



foreach ($places_multi['old'] as $oldplaces3 => $oldplace3) {
    
    $nodelevel5 = self::createNode($graph,["id" => $oldplace3['id'],"name" => $oldplace3['name'],"lon" => $oldplace3['lon'],
    "lat" => $oldplace['lat'],"price" => $oldplace3['price'],"placeType" => $oldplace3['placeType']]);
    $level5[] = $nodelevel5;
    
    foreach($level4 as $placeBnode){

        self::addWeightedEdge($placeBnode,$nodelevel5);
    

    }
}


//create level6 nightplace
$level6=[];
foreach ($places_multi['night'] as $nightplaces => $nightplace) {
    
    $nodelevel6 = self::createNode($graph,["id" => $nightplace['id'],"name" => $nightplace['name'],"lon" => $nightplace['lon'],
    "lat" => $nightplace['lat'],"price" => $nightplace['price'],"placeType" => $nightplace['placeType']]);
    $level6[] = $nodelevel6;
    
    foreach($level5 as $placeCnode){

        self::addWeightedEdge($placeCnode,$nodelevel6);
    

    }
}

// create level7 (for resturants)
$resturants_level2= []; 
foreach ($places_multi['Resturants'] as $Resturants2 => $Resturant2) {
    

    $nodelevel7 = self::createNode($graph,["id" => $Resturant2['id'],"name" => $Resturant2['name'],"lon" => $Resturant2['lon'],
    "lat" => $Resturant2['lat'],"price" => $Resturant2['price'],"placeType" => $Resturant2['placeType']]);
    $resturants_level2[] = $nodelevel7;

    foreach($level6 as $nightnode){

        self::addWeightedEdge($nightnode,$nodelevel7);
            
    }   

}

//create level8 (fakenode)

$end=["id" => "10","name" => "destination",
"lon"=> 11.251828422382225,
"lat"=> 40.803499350173176,
"price"=> 0,
"placeType"=> null,
];
$endnode = self::createNode($graph,$end);

foreach($resturants_level2 as $resturants3){

    self::addWeightedEdge($resturants3,$endnode );
    
    } 
       
   return $graph; 
    
} 

}
    
   
    





