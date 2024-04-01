<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Trip;

class TripController extends Controller
{
    function create_trip(){
        
        return("Trip is created");     
    }

    function delete_trip(){

        return("Trip is deleted");
    }

    function update_trip(){

        return("Trip is updated");
    }
}
