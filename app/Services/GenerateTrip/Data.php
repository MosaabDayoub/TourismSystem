<?php

namespace App\Services\GenerateTrip;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class Data{

 public static function format(array $place)      // A function for formatting the array of data we fetch it to array we can use in graph
 {
     return array_map(function($item) {

     
        $spacePosition = strpos($item['location'], ' ');       // search in string (location) about space position 

        $lat = substr($item['location'], 0, $spacePosition);     // cut the string (location) from beginning to space position

        $lon = substr($item['location'], $spacePosition + 1);     //cut the string (location) from space position to the end 

        if (!array_key_exists('price', $item)) {     // if price not exists set it to 0
            
            $item['price'] = 0; 

        }

        if (!array_key_exists('star', $item)) {     // if stars or  not exists set it to 0
            
            $item['stars'] = 0; 

        }

        if (!array_key_exists('desciption', $item)) {     // if stars or  not exists set it to 0
            
            $item['desciption'] = null; 

        }


        return [                        // how the array will look like:
             'id'  =>  $item['id'],
            'name' => $item['name'],
            'lat' => floatval($lat), 
            'lon' => floatval($lon),
            'price' => $item['price'],
            'stars' => $item['stars'],
            'Address' => $item['address'],
            'desciption' => $item['desciption'],

        ];
    }, $place);
 }
  
public static function fetchData(array $preferred,array $data,$cityname,$is_a_capital ) {    // A funcyion for fetch Data from DB depending on user preferences 

            //find the airport of this capital
            if($is_a_capital == true){
            $AirportQuery=DB::table('City')

            ->join('airport','City.city_id','=','airport.city_id')
            ->select('airport.*')
            ->where('city.name', '=', $cityname)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();

            $Airport = self::format($AirportQuery);
            $Airport[0]['placeType'] = "Airport";
            $places['Airport'] = $Airport;           // storage Airport of the capital array in places array
            }


            // fetch preferd places
            foreach($preferred as $placeType ){

                // switch is for test what user say about his preferreces and fech data depand on that
                switch($placeType){

                    case "naturalplaces":

                        //fetch natural places
                        $naturalplacesQuery=DB::table('naturalplace')

                        ->join('City','naturalplace.city_id','=','City.city_id')
                        ->select('naturalplace.*')
                        ->where('city.name', '=', $cityname)
                        ->where('id', '<', 3)
                        ->get()
                        ->map(function ($item) {
                            return (array) $item;
                        })->toArray();

                        $naturalplaces=self::format( $naturalplacesQuery);

                        foreach ($naturalplaces as $key => $natural) {
                            $naturalplaces[$key]['placeType'] = "natural"; // add type => natural for each element in array $naturalplaces
                        }
                        
                        $places['natural'] = $naturalplaces; // storage naturalplaces array in places array
                        
                        break;



                    case 'oldplaces':

                        //fetch old places
                        $oldplacesQuery=DB::table('oldplace')
                        ->join('City','oldplace.city_id','=','City.city_id')
                        ->select('oldplace.*')
                        ->where('city.name', '=', $cityname)
                        ->where('id', '<', 3)
                        ->get()
                        ->map(function ($item) {
                            return (array) $item;
                        })->toArray();

                        $oldplaces=self::format( $oldplacesQuery);
                        foreach ($oldplaces as $key => $old) {
                            $oldplaces[$key]['placeType'] = "old"; // add type=>old for each element in array shopping
                        }
                        
                        $places['old'] = $oldplaces; // storage oldplaces array in places array
                        
                        
                        break;



                        case 'shoppingplaces':

                        //fetch shopping places
                        $shoppingplacesQuery=DB::table('ShoopingPlace')

                        ->join('City','ShoopingPlace.city_id','=','City.city_id')
                        ->select('ShoopingPlace.*')
                        ->where('city.name', '=', $cityname)
                        ->where('id', '<', 3)
                        ->get()
                        ->map(function ($item) {
                            return (array) $item;
                        })->toArray();
                        $shopping=self::format( $shoppingplacesQuery);
                        foreach ($shopping as $key => $shop) {
                            $shopping[$key]['placeType'] = "shop"; // add type=>shop for each element in array shopping
                        }
                        
                        $places['shopping'] = $shopping; // storage shopping array in places array
                        
                        
                        break;
                    


                        case 'nightplaces':

                        // fetch night places
                        $nightplacesQuery=DB::table('nightplace')

                        ->join('City','nightplace.city_id','=','City.city_id')
                        ->select('nightplace.*')
                        ->where('city.name', '=', $cityname)
                        ->where('id', '<', 3)
                        ->get()
                        ->map(function ($item) {
                            return (array) $item;
                        })->toArray();

                        $nightplaces=self::format( $nightplacesQuery); 
                        foreach ($nightplaces as $key => $night) {
                            $nightplaces[$key]['placeType'] = "night"; // add type => shop for each element in array shopping
                        }
                        
                        $places['night'] = $nightplaces; // storage shopping array in places array
                        
                        break;
                                 
                }
            
            }

            //fetch resturants

            if (isset($data['preferedfood']))
            {
                $prefernce_food=array_keys($data['preferedfood'], true); //to create array from $Data['preferedfood']

                $ResturantsQuery=DB::table('resturant')
                ->join('City','resturant.city_id','=','City.city_id')
                ->select('resturant.*')
                ->where('City.name','=', $cityname)
                ->wherein('food_type', $prefernce_food) 
                ->get()
                ->map(function ($item) {
                    return (array) $item;
                })->toArray();

                $Resturants=self::format( $ResturantsQuery); 
                foreach ($Resturants as $key => $Resturant) {
                    $Resturants[$key]['placeType'] = "Resturant";     // add type => resturant for each element in array shopping
                }
                
                $places['Resturants'] = $Resturants;     // storage Resturants array in places array
                 
                }


                //fetch Hotels

                $HotelsQuery=DB::table('hotel')

                ->join('City','hotel.city_id','=','City.city_id')
                ->select('hotel.*')
                ->where('city.name', '=', $cityname)   // تعديل fromcty ==> capital og country 
                ->where('id', '<', 3)
                ->get()
                ->map(function ($item) {
                    return (array) $item;
                })->toArray();
                
                $Hotels=self::format( $HotelsQuery);
                foreach ($Hotels as $key => $Hotel) {
                    $Hotels[$key]['placeType'] = "Hotel";    // add type => Hotel for each element in array shopping
                }
                
                $places['Hotels'] = $Hotels;   // storege Hotels array in places array
                 

                
                $currentcityQuery = DB::table('City')->select('*')->where('name',$cityname)->first();     // fetch the current city information from database

                $currentcity = get_object_vars($currentcityQuery);  // to convert stdclass object to array
                
                $places['Currentcity'] = $currentcity;       // storege Hotels array in places array

                

                return $places;  // $places is array of array contain kay type -> value :array of places
                
    }

    

}


