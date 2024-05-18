<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\GenerateTripController;
use App\Http\Controllers\SearchController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/signup', UserController::class . '@signup');
Route::post('/login', UserController::class . '@login');
Route::put('/create_trip', TripController::class . '@create_trip')->middleware('auth:sanctum');
Route::put('/generate_trip', GenerateTripController::class . '@generate');

Route::post('/update-image', [UserController::class, "upload_image"]);
Route::put('/delete-image', [UserController::class, "delete_image"]);

Route::get('/search', [SearchController::class, 'search']);
Route::post('/get_all-cities', GenerateTripController::class . '@getallcities');
Route::post('/get_all-countries', GenerateTripController::class . '@getallcountries');
Route::put('/change-trip-place', TripController::class . '@changetripplace');
Route::delete('/delete-trip-place', TripController::class . '@deletetripplace');
