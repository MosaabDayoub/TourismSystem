<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;

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
            $newuser = User::Create([
                "name" => $request->input("name"),
                "image" => $request->input("image"),
                "email" => $request->input("email"),
                "password" => Hash::make($request->input("password")),
                "age" => $request->input("age"),
            ]);
            $token1 = $newuser->createToken("auth_token");

            return response()->json([
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $newuser->id,
                    'name' => $newuser->name,
                    'email' => $newuser->email,
                    'image' => null,
                    'age' => $newuser->age,
                    'token' => $token1->plainTextToken,
                ]
            ], Response::HTTP_OK);
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

                $token1 = $user->createToken("auth_token");

                return response()->json([
                    "message" => "Login successful! Welcome back.",
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'image' => $user->image_refrence,
                        'age' => $user->age,
                        'token' => $token1->plainTextToken,
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

    public function upload_image(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'userId' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'image not valid'], 400);
        }

        $user = User::where('id', '=', $request->userId)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        try {
            // Check if the user already has a profile picture
            if ($user->image_refrence) {
                Storage::delete($user->image_refrence);
            }

            $path = $request->file('image')->store('public/profile_pictures');

            // Update the user's profile picture field in the database
            $user->image_refrence = $path;
            $user->save();

            $imageUrl = Storage::url($path);

            return response()->json([
                'image' => $imageUrl,
                'message' => 'Profile picture uploaded successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                500
            );
        }
    }

    public function delete_image(Request $request)
    {
        $user = User::where('id', '=', $request->userId)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        try {
            // Check if the user already has a profile picture
            if ($user->image_refrence) {
                Storage::delete($user->image_refrence);
            }

            // Update the user's profile picture field in the database
            $user->image_refrence = null;
            $user->save();

            return response()->json([
                'image' => $user->image_refrence,
                'message' => 'Profile picture deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                500
            );
        }
    }


}