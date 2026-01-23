<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // -------------------------
    // Register (always STAFF)
    // -------------------------
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'username' => 'required|string|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => 'staff',
        ]);

        return response()->json([
            'user' => $user,
            'token' => JWTAuth::fromUser($user),
        ], 201);
    }

    // -------------------------
    // Login
    // -------------------------
    public function login(Request $request)
    {
        if (!$token = JWTAuth::attempt($request->only('username', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'token' => $token,
            'user' => auth()->user(),
        ]);
    }

    // -------------------------
    // Current user
    // -------------------------
    public function me()
    {
        return auth()->user();
    }

    // -------------------------
    // Logout
    // -------------------------
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Logged out']);
    }

    // -------------------------
    // ADMIN: list users
    // -------------------------
    public function index()
    {
        return User::select('id', 'name', 'username', 'role')->get();
    }
    public function users()
    {
        return User::select('id', 'name', 'username', 'role')->get();
    }

    // -------------------------
    // ADMIN: update role
    // -------------------------
    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => ['required', Rule::in(['admin', 'staff'])],
        ]);

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot change your own role'], 403);
        }

        $user->update(['role' => $request->role]);

        return response()->json(['message' => 'Role updated', 'user' => $user]);
    }
}
