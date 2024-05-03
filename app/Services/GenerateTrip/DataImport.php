<?php

namespace App\Services\GenerateTrip;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\GenerateTripController;

class DataImport
{
    public static function calculatePlaceCosts($budgetOfDay, $preferred, $N_person)
    {
        $Totalcost = 0;
        foreach ($preferred as $preferred_place) {

            if ($preferred_place == 'natural') {
                continue;
            }
            if ($preferred_place == 'shopping') {
                ${$preferred_place . "_avg"} = 118;
                continue;
            }


            if($preferred_place == 'resturant' || $preferred_place == 'hotel') {
                ${$preferred_place . "_avg"} = DB::table("{$preferred_place}")->distinct()->avg('price');

            } else {
                ${$preferred_place . "_avg"} = DB::table("{$preferred_place}place")->distinct()->avg('price');

            }
            if($preferred_place == 'resturant') {
                $Totalcost += ${$preferred_place . "_avg"} * 2;
            } else {
                $Totalcost += ${$preferred_place . "_avg"};
            }
        }

        $costs = [];

        foreach ($preferred as $preferred_place) {

            if ($preferred_place != "natural" && isset(${$preferred_place . "_avg"})) {
                $place_cost = floor(($budgetOfDay * (${$preferred_place . "_avg"} / $Totalcost)) * 100 / 100);
                $costs[$preferred_place] = $place_cost;
                $costs[$preferred_place . '_per_person'] = floor($place_cost / $N_person);
            }
        }

        return $costs;
    }

    public static function importData(array $preferred, array $data, $cityname, $travelmethod, $budgetofday, $N_person, $visitedplaces, $placesofuser)    // A funcyion for fetch Data from DB depending on user preferences
    {
        $i = 0;
        $budgetofday -= 70;
        $budgetofday = $budgetofday / $N_person;
        $preferred[] = "resturant";
        $preferred[] = "hotel";
        $place_costs = self::calculatePlaceCosts($budgetofday, $preferred, $N_person);

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
            $Airport[0]['time'] = 1;
            $places['Airport'] = $Airport;           // storage Airport of the capital array in places array
        }


        // fetch preferd places from Database
        foreach($preferred as $placeType) {
            $i++;
            ${"SelectedPlaces" . $i} = [];

            if($placeType == 'resturant' || $placeType == 'hotel') {
                continue;
            }
            if(key_exists($cityname, $placesofuser) &&  key_exists($placeType, $placesofuser[ $cityname]) && !empty($placesofuser[ $cityname][$placeType])) {
                foreach($placesofuser[ $cityname][$placeType] as $placechoosen) {

                    if(in_array($placechoosen['name'], $visitedplaces)) {
                        continue;
                    }

                    ${"SelectedPlaces" . $i} = DB::table("{$placeType}place")
                    ->join('City', "{$placeType}place.city_id", '=', 'City.city_id')
                    ->select($placeType.'place.*')
                    ->where('City.name', '=', $cityname)
                    ->where($placeType.'place.id', '=', $placechoosen['id'])
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();

                }

            }
            if(empty(${"SelectedPlaces" . $i})) {
                if($placeType == "natural" || $placeType == "shopping") {
                    ${"SelectedPlaces" . $i} = DB::table("{$placeType}place")
                    ->join('City', "{$placeType}place.city_id", '=', 'City.city_id')
                    ->select($placeType.'place.*')
                    ->where('City.name', '=', $cityname)
                    ->whereNotIn($placeType.'place.name', $visitedplaces)
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();
                } else { // Collect places and get the smallest difference in price
                    $SelectedPlacesWithDifferences = DB::table("{$placeType}place")
                    ->join('City', "{$placeType}place.city_id", '=', 'City.city_id')
                    ->where('City.name', '=', $cityname)
                    ->whereNotIn($placeType.'place.name', $visitedplaces)
                    ->select("{$placeType}place.*", DB::raw("ABS({$placeType}place.price - {$place_costs[$placeType ]}) AS price_difference"))
                    ->get();

                    // Find the smallest price difference based on the collected records
                    $closestPriceDifference = $SelectedPlacesWithDifferences->min('price_difference');

                    // Filter records to bring in places with the smallest price difference
                    ${"SelectedPlaces" . $i} = $SelectedPlacesWithDifferences->filter(function ($selectedplaces) use ($closestPriceDifference) {
                        return $selectedplaces->price_difference == $closestPriceDifference;
                    })->values()

                        ->map(function ($item) {
                            return (array) $item;
                        })->toArray();

                }
            }
            foreach (${"SelectedPlaces" . $i} as $key => $place) {
                if($placeType == "natural") {
                    ${"SelectedPlaces" . $i}[$key]['price'] = 0;
                }
                if($placeType == "shopping") {
                    ${"SelectedPlaces" . $i}[$key]['price'] = $place_costs[$placeType ];
                }
                ${"SelectedPlaces" . $i}[$key]['placeType'] = $placeType; // add type  for each element in array storage $selectedplaces
            }

            $places[$placeType] = ${"SelectedPlaces" . $i}; // storage $selectedplaces array in places array

            if(($key = array_search($placeType, $preferred)) !== false) {
                unset($preferred[$key]);
            }
            $budgetofday -= $places[$placeType][0]['price'];
            $place_costs = self::calculatePlaceCosts($budgetofday, $preferred, $N_person);

        }
        if (isset($data['preferedfood'])) {
            $preference_food = array_keys($data['preferedfood'], true);
        }
        //fetch resturants
        for ($i = 1 ; $i <= 2 ;$i++) {
            ${"Resturants" . $i} = [];
            if (count($preference_food) > 1) {
                $halfwayPoint = ceil(count($preference_food) / 2);
                $firstHalf = array_slice($preference_food, 0, $halfwayPoint);
                $secondHalf = array_slice($preference_food, $halfwayPoint);
            } else {
                $firstHalf = $preference_food;
                $secondHalf = $preference_food;
            }
            if($i == 1) {
                $preference_food = $firstHalf;
            } else {
                $preference_food = $secondHalf;
            }
            if(key_exists($cityname, $placesofuser) &&  key_exists('Resturants', $placesofuser[$cityname]) && !empty($placesofuser[$cityname]['Resturants'])) {
                foreach($placesofuser[$cityname]['Resturants'] as $Resturant) {


                    if(in_array($Resturant['name'], $visitedplaces)) {
                        continue ;
                    }

                    ${"Resturants" . $i} = DB::table('resturant')
                    ->join('City', 'resturant.city_id', '=', 'City.city_id')
                    ->where('resturant.id', '=', $Resturant['id'])
                    ->where('City.name', '=', $cityname)
                    ->whereNotIn('resturant.name', $visitedplaces)
                    ->select('resturant.*')
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();

                }
            }
            if(!empty(${"Resturants" . $i})) {
                $visitedtype = ${"Resturants" . $i}[0]['food_type'];

                if(($key = array_search($visitedtype, $preference_food)) !== false && count($preference_food) > 1) {
                    unset($preference_food[$key]);
                }
            } else {
                // Collect restaurants and get the smallest difference in price
                $restaurantsWithDifferences = DB::table('resturant')
                    ->join('City', 'resturant.city_id', '=', 'City.city_id')
                    ->whereNotIn('resturant.name', $visitedplaces)
                    ->whereIn('food_type', $preference_food)
                    ->where('City.name', '=', $cityname)
                    ->select('resturant.*', DB::raw("ABS(resturant.price - {$place_costs['resturant']})  as price_difference"))
                    ->get();

                // // Find the smallest price difference based on the collected records
                $closestPriceDifference = $restaurantsWithDifferences->min('price_difference');

                // // Filter records to bring in restaurants with the smallest price difference
                ${"Resturants" . $i} = $restaurantsWithDifferences->filter(function ($restaurant) use ($closestPriceDifference) {
                    return $restaurant->price_difference == $closestPriceDifference;
                })->values()

                    ->map(function ($item) {
                        return (array) $item;
                    })->toArray();

            }
            foreach (${"Resturants" . $i} as $key => $Resturant) {
                ${"Resturants" . $i}[$key]['placeType'] = "Resturant";     // add type => resturant for each element in array $Resturants
                ${"Resturants" . $i}[$key]['time'] = 1;
                if(!in_array($Resturant['name'], $visitedplaces)) {
                    $visitedplaces[] = $Resturant['name'];
                }
            }

            $places['Resturants'.$i] = ${"Resturants" . $i};     // storage Resturants array in places array
            $budgetofday -= $places['Resturants'.$i][0]['price'];

        }
        if(($key = array_search('resturant', $preferred)) !== false) {
            unset($preferred[$key]);
        }

        $place_costs = self::calculatePlaceCosts($budgetofday, $preferred, $N_person);


        //fetch Hotels
        if(key_exists($cityname, $placesofuser) &&  key_exists('Hotels', $placesofuser[$cityname]) && !empty($placesofuser[$cityname]['Hotels'])) {
            foreach($placesofuser[$cityname]['Hotels'] as $hotel) {
                if(in_array($hotel, $visitedplaces)) {
                    continue;
                }

                $Hotels = DB::table('hotel')
                ->join('City', 'hotel.city_id', '=', 'City.city_id')
                ->where('hotel.id', '=', $hotel['id'])
                ->where('City.name', '=', $cityname)
                ->whereNotIn('hotel.name', $visitedplaces)
                ->select('hotel.*')
                ->get()
                ->map(function ($item) {
                    return (array) $item;
                })->toArray();

            }
        } if(empty($Hotels)) {
            $HotelsWithDifferences = DB::table('hotel')
            ->join('City', 'hotel.city_id', '=', 'City.city_id')
            ->where('City.name', '=', $cityname)
            ->whereNotIn('hotel.name', $visitedplaces)
            ->select('hotel.*', DB::raw("ABS(hotel.price - {$place_costs['hotel']} ) as price_difference"))
            ->get();

            $closestPriceDifference = $HotelsWithDifferences->min('price_difference');

            $Hotels = $HotelsWithDifferences->filter(function ($Hotel) use ($closestPriceDifference) {
                return $Hotel->price_difference == $closestPriceDifference;
            })->values()
            ->map(function ($item) {
                return (array) $item;
            })->toArray();
        }
        foreach ($Hotels as $key => $Hotel) {
            $Hotels[$key]['placeType'] = "Hotel";    // add type => Hotel for each element in array $Hotels
            $Hotels[$key]['time'] = 2;
        }

        $places['Hotels'] = $Hotels;   // storege Hotels array in places array


        $currentcityQuery = DB::table('City')->select('*')->where('name', $cityname)->first();     // fetch the current city information from database

        $currentcity = get_object_vars($currentcityQuery);  // to convert stdclass object to array

        $places['Currentcity'] = $currentcity;       // storege currentcity array in places array


        return $places;  // $places is array of array contain kay type -> value :array of places

    }
}
