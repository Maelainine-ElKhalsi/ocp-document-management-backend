<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Register
    public function register(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|unique:users',
            'password'   => 'required|string|min:6|confirmed',
            'role'       => 'sometimes|in:admin,chef_service,technicien',
            'service_id' => 'sometimes|nullable|exists:services,id',
        ]);

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => $request->role ?? 'technicien',
            'service_id' => $request->service_id,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Compte créé avec succès',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    // Login
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email ou mot de passe incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user'    => $user->load('service'),
            'token'   => $token,
        ]);
    }

    // Me
    public function me(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'technicien') {
            return response()->json($user->load([
                'axes' => function ($q) {
                    $q->with('service')->withCount('dossiers');
                },
            ]));
        }

        return response()->json($user->load(['service.axes']));
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }
}
