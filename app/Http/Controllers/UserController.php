<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function signup(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'required|email|unique:user,email',
                'password' => 'required|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create the user
            $newuser=User::Create([
              "name"=>$request->input("name"),
              "image"=>$request->input("image"),
              "email"=>$request->input("email"),
              "password"=>$request->input("password"),
            ]);
            $token1=$newuser->createtoken("auth_token");
            $token =response()->json(["token" => "token:" .$token1->plainTextToken]);
            
            return response()->json([
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $newuser->id,
                    'name' => $newuser->name,
                    'email' => $newuser->email,
                    'image' => null,
                    'token' => $token,
                ]
            ], Response::HTTP_OK);
        } catch (ValidationException $e) {

            return response()->json([
                'message' => 'some information are not valid',
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            // \Log::error('User registration error: ' . $e->getMessage());

            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function login(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'email' => 'required',
                'password' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        'message' => $validator->errors()->first(),
                    ],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $user = User::where('email', $request->email)->first();

            // if the user exists in the DB
            if ($user && Hash::check($request->password, $user->password)) {
              
                $token1=$user->createtoken("auth_token");
                $token =response()->json(["token" => "token:" .$token1->plainTextToken]);
                return response()->json([
                    "message" => "Login successful! Welcome back.",
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'image' => null,
                        'token' => $token,
                    ],
                ], Response::HTTP_OK);
            } else {
                return response()->json([
                    "message" => "Wrong email or password",
                ], Response::HTTP_BAD_REQUEST);
            }

        } catch (ValidationException $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

}