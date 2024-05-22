<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Trip;
use App\Http\Controllers\GenerateTripController;
use App\Services\GenerateTrip\CustomGraph;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    public function delete_trip()
    {

        return("Trip is deleted");
    }

    public function changetripplace(Request $request)
    {
        try {
            $Data = $request->all();


            $previosplaceQuery = DB::table($Data['newPlaceType'])
            ->select("{$Data['newPlaceType']}.*")
            ->where("{$Data['newPlaceType']}.id", '=', function ($query) use (&$Data) {
                $query->select('place_id')
                    ->from('dayplaces')
                    ->where('index', '=', $Data['placeIndex'] - 1)
                    ->first();
            })
            ->first();
            $previosplace = get_object_vars($previosplaceQuery);  // to convert stdclass object to array


            $nextplaceQuery = DB::table($Data['newPlaceType'])
            ->select("{$Data['newPlaceType']}.*")
            ->where("{$Data['newPlaceType']}.id", '=', function ($query) use (&$Data) {
                $query->select('place_id')
                    ->from('dayplaces')
                    ->where('index', '=', $Data['placeIndex'] + 1)
                    ->first();
            })
            ->first();
            $nextplace = get_object_vars($nextplaceQuery);  // to convert stdclass object to array

            $newplaceQuery = DB::table($Data['newPlaceType'])
            ->select("{$Data['newPlaceType']}".".*")
            ->where("{$Data['newPlaceType']}".".id", '=', $Data['newPlaceId'])
            ->first();
            $newplace = get_object_vars($newplaceQuery);  // to convert stdclass object to array
            if($Data['newPlaceType'] == "naturalplace") {
                $newplace['price'] = 0;
            }
            if($Data['newPlaceType'] == "shoppingpplace") {
                $newplace['price'] = 20;
            }
            // return $newplace;
            $oldplaceQuery = DB::table($Data['oldPlaceType'])
            ->select("{$Data['oldPlaceType']}".".*")
            ->where("{$Data['oldPlaceType']}".".id", '=', $Data['oldPlaceId'])
            ->first();
            $oldplace = get_object_vars($oldplaceQuery);  // to convert stdclass object to array

            if($Data['oldPlaceType'] == "naturalplace") {
                $oldplace['price'] = 0;
            }
            if($Data['oldPlaceType'] == "shoppingpplace") {
                $oldplace['price'] = 20;
            }



            $trip_N_people_Query = DB::table('trip')
            ->select('trip.number_of_people')
            ->where('trip.id', '=', $Data['tripid'])
            ->first();
            $trip_N_people = get_object_vars($trip_N_people_Query);  // to convert stdclass object to array


            $previosplace_location = CustomGraph::getlocation($previosplace['location']);
            $newplace_location =  CustomGraph::getlocation($newplace['location']);
            $nextplace_location =  CustomGraph::getlocation($nextplace['location']);

            $newplace_pre_distance = ceil(GenerateTripController::haversineDistance($previosplace_location, $newplace_location));
            $newplace_transport_method = CustomGraph::travell_method($newplace_pre_distance);
            $newplace_mony_amount = $trip_N_people['number_of_people'] * $newplace['price'];


            $affected1 = DB::table('dayplaces')
            -> where([
                ['day_id', '=', $Data['dayId']],
                ['index', '=', $Data['placeIndex']]
            ])
            ->update(
                ['place_id' => $Data['newPlaceId'],
                'place_type' => $Data['newPlaceType'],
                'pre_distance' => $newplace_pre_distance,
                'transport_method' => $newplace_transport_method,
                'money_amount' =>  $newplace_mony_amount]
            );

            $nextplace_newpre_distance = ceil(GenerateTripController::haversineDistance($newplace_location, $nextplace_location));
            $nextplace_newtransport_method = CustomGraph::travell_method($nextplace_newpre_distance);

            $affected2 = DB::table('dayplaces')
            -> where([
                ['day_id', '=', $Data['dayId']],
                ['index', '=', $Data['placeIndex'] + 1]
            ])
            ->update(
                ['pre_distance' => $nextplace_newpre_distance,
                'transport_method' => $nextplace_newtransport_method ,
                ]
            );

            $nextplace_oldpre_distance_Query = DB::table('dayplaces')
            ->select('pre_distance')
            ->where('index', '=', $Data['placeIndex'] + 1)
            ->first();
            $nextplace_oldpre_distance = get_object_vars($nextplace_oldpre_distance_Query);  // to convert stdclass object to array

            $oldplace_pre_distance_Query = DB::table('dayplaces')
            ->select('pre_distance')
            ->where('index', '=', $Data['placeIndex'])
            ->first();
            $oldplace_pre_distance  = get_object_vars($oldplace_pre_distance_Query);  // to convert stdclass object to array


            $newplace_transport_cost = ($newplace_pre_distance / 1000) * 1.6;
            $oldplace_transport_cost = ($oldplace_pre_distance['pre_distance'] / 1000) * 1.6;
            $nextplace_oldtransport_cost = ($nextplace_oldpre_distance ['pre_distance'] / 1000) * 1.6;
            $nextplace_newtransport_cost = ($nextplace_newpre_distance / 1000) * 1.6;

            $oldplace_mony_amount = $trip_N_people['number_of_people'] * $oldplace['price'];

            $trip_cost_Query = $trip_N_people_Query = DB::table('trip')
            ->select('trip.trip_cost')
            ->where('trip.id', '=', $Data['tripid'])
            ->first();

            $trip_cost = get_object_vars($trip_cost_Query);  // to convert stdclass object to array


            $new_trip_cost = ceil($trip_cost['trip_cost'] - $oldplace_mony_amount + $newplace_mony_amount - $oldplace_transport_cost + $newplace_transport_cost - $nextplace_oldtransport_cost + $nextplace_newtransport_cost);

            $affected3 = DB::table('trip')
            -> where('trip.id', '=', $Data['tripid'])
            ->update(['trip.trip_cost' => $new_trip_cost]);

            $day_cost_Query = $trip_N_people_Query = DB::table('tripday')
            ->select('tripday.day_cost')
            ->where('tripday.id', '=', $Data['dayId'])
            ->first();

            $day_cost = get_object_vars($day_cost_Query);  // to convert stdclass object to array

            $new_day_cost = ceil($day_cost['day_cost'] - $oldplace_mony_amount + $newplace_mony_amount - $oldplace_transport_cost + $newplace_transport_cost - $nextplace_oldtransport_cost + $nextplace_newtransport_cost);

            $affected4 = DB::table('tripday')
            -> where('tripday.id', '=', $Data['dayId'])
            ->update(['tripday.day_cost' => $new_day_cost]);

            if ($affected1 == false or $affected2 == false or $affected3 == false or $affected4 == false) {
                return response()->json(
                    ['error' => "Failed to change place."],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response()->json([
                "message" => "The place has been changed successfully",
            ], Response::HTTP_OK);

        } catch (\Exception $error) {
            return response()->json(
                ['error' => $error->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

    }

    public function deletetripplace(Request $request)
    {

        try {
            $Data = $request->all();


            $previosplaceQuery = DB::table($Data['PlaceType'])
            ->select("{$Data['PlaceType']}.*")
            ->where("{$Data['PlaceType']}.id", '=', function ($query) use (&$Data) {
                $query->select('place_id')
                    ->from('dayplaces')
                    ->where('index', '=', $Data['placeIndex'] - 1)
                    ->first();
            })
            ->first();
            $previosplace = get_object_vars($previosplaceQuery);  // to convert stdclass object to array


            $nextplaceQuery = DB::table($Data['newPlaceType'])
            ->select("{$Data['newPlaceType']}.*")
            ->where("{$Data['newPlaceType']}.id", '=', function ($query) use (&$Data) {
                $query->select('place_id')
                    ->from('dayplaces')
                    ->where('index', '=', $Data['placeIndex'] + 1)
                    ->first();
            })
            ->first();
            $nextplace = get_object_vars($nextplaceQuery);  // to convert stdclass object to array

            /*        $newplaceQuery = DB::table($Data['newPlaceType'])
                    ->select("{$Data['newPlaceType']}".".*")
                    ->where("{$Data['newPlaceType']}".".id", '=', $Data['newPlaceId'])
                    ->first();
                    $newplace = get_object_vars($newplaceQuery);  // to convert stdclass object to array
                    if($Data['newPlaceType'] == "naturalplace") {
                        $newplace['price'] = 0;
                    }
                    if($Data['newPlaceType'] == "shoppingpplace") {
                        $newplace['price'] = 20;
                    }
                    // return $newplace;*/
            $oldplaceQuery = DB::table($Data['PlaceType'])
            ->select("{$Data['PlaceType']}".".*")
            ->where("{$Data['PlaceType']}".".id", '=', $Data['PlaceId'])
            ->first();
            $oldplace = get_object_vars($oldplaceQuery);  // to convert stdclass object to array

            if($Data['oldPlaceType'] == "naturalplace") {
                $oldplace['price'] = 0;
            }
            if($Data['oldPlaceType'] == "shoppingpplace") {
                $oldplace['price'] = 20;
            }



            $trip_N_people_Query = DB::table('trip')
            ->select('trip.number_of_people')
            ->where('trip.id', '=', $Data['tripid'])
            ->first();
            $trip_N_people = get_object_vars($trip_N_people_Query);  // to convert stdclass object to array


            $previosplace_location = CustomGraph::getlocation($previosplace['location']);
            $newplace_location =  CustomGraph::getlocation($newplace['location']);
            $nextplace_location =  CustomGraph::getlocation($nextplace['location']);

            $newplace_pre_distance = ceil(GenerateTripController::haversineDistance($previosplace_location, $newplace_location));
            $newplace_transport_method = CustomGraph::travell_method($newplace_pre_distance);
            $newplace_mony_amount = $trip_N_people['number_of_people'] * $newplace['price'];


            $affected1 = DB::table('dayplaces')
            -> where([
                ['day_id', '=', $Data['dayId']],
                ['index', '=', $Data['placeIndex']]
            ])
            ->update(
                ['place_id' => $Data['newPlaceId'],
                'place_type' => $Data['newPlaceType'],
                'pre_distance' => $newplace_pre_distance,
                'transport_method' => $newplace_transport_method,
                'money_amount' =>  $newplace_mony_amount]
            );

            $nextplace_newpre_distance = ceil(GenerateTripController::haversineDistance($newplace_location, $nextplace_location));
            $nextplace_newtransport_method = CustomGraph::travell_method($nextplace_newpre_distance);

            $affected2 = DB::table('dayplaces')
            -> where([
                ['day_id', '=', $Data['dayId']],
                ['index', '=', $Data['placeIndex'] + 1]
            ])
            ->update(
                ['pre_distance' => $nextplace_newpre_distance,
                'transport_method' => $nextplace_newtransport_method ,
                ]
            );

            $nextplace_oldpre_distance_Query = DB::table('dayplaces')
            ->select('pre_distance')
            ->where('index', '=', $Data['placeIndex'] + 1)
            ->first();
            $nextplace_oldpre_distance = get_object_vars($nextplace_oldpre_distance_Query);  // to convert stdclass object to array

            $oldplace_pre_distance_Query = DB::table('dayplaces')
            ->select('pre_distance')
            ->where('index', '=', $Data['placeIndex'])
            ->first();
            $oldplace_pre_distance  = get_object_vars($oldplace_pre_distance_Query);  // to convert stdclass object to array


            $newplace_transport_cost = ($newplace_pre_distance / 1000) * 1.6;
            $oldplace_transport_cost = ($oldplace_pre_distance['pre_distance'] / 1000) * 1.6;
            $nextplace_oldtransport_cost = ($nextplace_oldpre_distance ['pre_distance'] / 1000) * 1.6;
            $nextplace_newtransport_cost = ($nextplace_newpre_distance / 1000) * 1.6;

            $oldplace_mony_amount = $trip_N_people['number_of_people'] * $oldplace['price'];

            $trip_cost_Query = $trip_N_people_Query = DB::table('trip')
            ->select('trip.trip_cost')
            ->where('trip.id', '=', $Data['tripid'])
            ->first();

            $trip_cost = get_object_vars($trip_cost_Query);  // to convert stdclass object to array


            $new_trip_cost = ceil($trip_cost['trip_cost'] - $oldplace_mony_amount + $newplace_mony_amount - $oldplace_transport_cost + $newplace_transport_cost - $nextplace_oldtransport_cost + $nextplace_newtransport_cost);

            $affected3 = DB::table('trip')
            -> where('trip.id', '=', $Data['tripid'])
            ->update(['trip.trip_cost' => $new_trip_cost]);

            $day_cost_Query = $trip_N_people_Query = DB::table('tripday')
            ->select('tripday.day_cost')
            ->where('tripday.id', '=', $Data['dayId'])
            ->first();

            $day_cost = get_object_vars($day_cost_Query);  // to convert stdclass object to array

            $new_day_cost = ceil($day_cost['day_cost'] - $oldplace_mony_amount + $newplace_mony_amount - $oldplace_transport_cost + $newplace_transport_cost - $nextplace_oldtransport_cost + $nextplace_newtransport_cost);

            $affected4 = DB::table('tripday')
            -> where('tripday.id', '=', $Data['dayId'])
            ->update(['tripday.day_cost' => $new_day_cost]);

            if ($affected1 == false or $affected2 == false or $affected3 == false or $affected4 == false) {
                return response()->json(
                    ['error' => "Failed to change place."],
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response()->json([
                "message" => "The place has been changed successfully",
            ], Response::HTTP_OK);

        } catch (\Exception $error) {
            return response()->json(
                ['error' => $error->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

    }
}


//}
