<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\NaturalPlace;
use App\Models\Resturant;
use App\Models\OldPlace;
use App\Models\NightPlace;
use App\Models\ShoopingPlace;
use App\Models\City;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        try {
            $placeType = $request->query('placeType');
            $placeName = $request->query('placeName');

            if (!$placeName) {
                return response()->json(
                    ['error' => 'Missing required parameter "placeName".'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $places = [];

            $placeModels = [
                'hotel' => Hotel::class,
                'restaurant' => Resturant::class,
                'old_place' => OldPlace::class,
                'night_place' => NightPlace::class,
                'shopping_place' => ShoopingPlace::class,
                'natural_place' => NaturalPlace::class,
            ];

            if ($placeType) {
                if (!array_key_exists($placeType, $placeModels)) {
                    return response()->json(
                        ['error' => 'Invalid place type.'],
                        Response::HTTP_BAD_REQUEST
                    );
                }

                $places = $placeModels[$placeType]::where('name', 'like', "%$placeName%")
                    ->get()
                    ->map(function ($place) use ($placeType) {
                        $place->placeType = $placeType;
                        return $place;
                    })
                    ->all();
            } else {
                foreach ($placeModels as $placeType => $model) {
                    $results = $model::where('name', 'like', "%$placeName%")->get();

                    foreach ($results as $result) {
                        $result->placeType = $placeType;
                        $places[] = $result;
                    }
                }
            }

            if (count($places) === 0) {
                return response()->json(
                    ['error' => "There is no place with the name '$placeName'"],
                    Response::HTTP_NOT_FOUND
                );
            }

            foreach ($places as $place) {
                $city = City::find($place->city_id);

                if ($city) {
                    $place->cityName = $city->name;
                    $place->cityId = $place->city_id;
                    unset($place->city_id);
                }
            }

            return response()->json(['places' => $places], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}