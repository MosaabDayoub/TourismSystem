<?php

namespace App\Services\GenerateTrip;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Data
{
    public static function fetchData(array $preferred, array $data, $cityname, $travelmethod)    // A funcyion for fetch Data from DB depending on user preferences
    {
        //find the airport of this capital
        if($travelmethod === "plane") {
            $Airport = DB::table('City')

            ->join('airport', 'City.city_id', '=', 'airport.city_id')
            ->select('airport.*')
            ->where('city.name', '=', $cityname)

            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();

            $Airport[0]['placeType'] = "Airport";
            $places['Airport'] = $Airport;           // storage Airport of the capital array in places array
        }


        // fetch preferd places
        foreach($preferred as $placeType) {

            // switch is for test what user say about his preferreces and fech data depand on that
            switch($placeType) {

                case "naturalplaces":

                    //fetch natural places
                    $naturalplaces = DB::table('naturalplace')

                    ->join('City', 'naturalplace.city_id', '=', 'City.city_id')
                    ->select('naturalplace.*')
                    ->where('city.name', '=', $cityname)
                   // ->where('id', '<', 8)
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();

                    foreach ($naturalplaces as $key => $natural) {
                        $naturalplaces[$key]['placeType'] = "natural"; // add type => natural for each element in array $naturalplaces
                    }

                    $places['natural'] = $naturalplaces; // storage naturalplaces array in places array

                    break;


                case 'oldplaces':

                    //fetch old places
                    $oldplaces = DB::table('oldplace')
                    ->join('City', 'oldplace.city_id', '=', 'City.city_id')
                    ->select('oldplace.*')
                    ->where('city.name', '=', $cityname)
                    //->where('id', '<', 8)
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();

                    foreach ($oldplaces as $key => $old) {
                        $oldplaces[$key]['placeType'] = "old"; // add type=>old for each element in array shopping
                    }

                    $places['old'] = $oldplaces; // storage oldplaces array in places array


                    break;



                case 'shoopingplaces':

                    //fetch shooping places
                    $shopping = DB::table('ShoopingPlace')

                    ->join('City', 'ShoopingPlace.city_id', '=', 'City.city_id')
                    ->select('ShoopingPlace.*')
                    ->where('city.name', '=', $cityname)
                    //->where('id', '<', 8)
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();

                    foreach ($shopping as $key => $shop) {
                        $shopping[$key]['placeType'] = "shooping"; // add type=>shop for each element in array shopping
                    }

                    $places['shooping'] = $shopping; // storage shopping array in places array


                    break;



                case 'nightplaces':

                    // fetch night places
                    $nightplaces = DB::table('nightplace')

                    ->join('City', 'nightplace.city_id', '=', 'City.city_id')
                    ->select('nightplace.*')
                    ->where('city.name', '=', $cityname)
                   // ->where('id', '<', 10)
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();

                    foreach ($nightplaces as $key => $night) {
                        $nightplaces[$key]['placeType'] = "night"; // add type => shop for each element in array shopping
                    }

                    $places['night'] = $nightplaces; // storage shopping array in places array

                    break;

            }

        }

        //fetch resturants

        if (isset($data['preferedfood'])) {
            $prefernce_food = array_keys($data['preferedfood'], true); //to create array from $Data['preferedfood']

            $Resturants = DB::table('resturant')
            ->join('City', 'resturant.city_id', '=', 'City.city_id')
            ->select('resturant.*')
            ->where('City.name', '=', $cityname)
           // ->where('id', '<', 3)
            ->wherein('food_type', $prefernce_food)
            ->get()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();

            foreach ($Resturants as $key => $Resturant) {
                $Resturants[$key]['placeType'] = "Resturant";     // add type => resturant for each element in array shopping
            }

            $places['Resturants'] = $Resturants;     // storage Resturants array in places array

        }

        //fetch Hotels

        $Hotels = DB::table('hotel')

        ->join('City', 'hotel.city_id', '=', 'City.city_id')
        ->select('hotel.*')
        ->where('city.name', '=', $cityname)
       // ->where('id', '<', 8)
        ->get()
        ->map(function ($item) {
            return (array) $item;
        })->toArray();

        foreach ($Hotels as $key => $Hotel) {
            $Hotels[$key]['placeType'] = "Hotel";    // add type => Hotel for each element in array shopping
        }

        $places['Hotels'] = $Hotels;   // storege Hotels array in places array



        $currentcityQuery = DB::table('City')->select('*')->where('name', $cityname)->first();     // fetch the current city information from database

        $currentcity = get_object_vars($currentcityQuery);  // to convert stdclass object to array

        $places['Currentcity'] = $currentcity;       // storege currentcity array in places array


        return $places;  // $places is array of array contain kay type -> value :array of places

    }



}
