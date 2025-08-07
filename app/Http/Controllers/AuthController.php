<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\User;
use App\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign default role to user
        $user->roles()->attach(Role::where('name', 'user')->first());

        // Generate token
        $token = $user->createToken('main')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token
        ], 201);
    }

    public function login(Request $request)
       {
        // 1. Validate the incoming request
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Attempt login
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

          // 4. Get the user
        $user = $request->user()->load('roles');

        // 5. Create a token for the user
        $token = $user->createToken('main')->plainTextToken;

        // 6. Return the authenticated user
    return response()->json([
        'message' => 'Login successful',
        'token' => $token,
        'user' => $user
    ])->header('Authorization', 'Bearer ' . $token);
    }

 // Logout method
public function logout(Request $request)
{
    // 1. Revoke the current user's token and delete it from the database 
    /** @var PersonalAccessToken $token */
    $token = $request->user()->currentAccessToken();

    $token?->delete();

    return response()->json([
        'message' => 'Logged out successfully'
    ]);
}

// Get authenticated user details
        public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
