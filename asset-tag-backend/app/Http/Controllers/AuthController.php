<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
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

        // Log registration
        ActivityLog::create([
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'registered',
            'module' => 'User',
            'record_id' => $user->id,
            'old_data' => null,
            'new_data' => [
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
            ],
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

        // Get the authenticated user
        $user = auth()->user();

        // Log successful login
        ActivityLog::create([
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'logged in',
            'module' => 'Authentication',
            'record_id' => $user->id,
            'old_data' => null,
            'new_data' => [
                'login_time' => now()->format('Y-m-d H:i:s'),
                'ip_address' => $request->ip(),
            ],
        ]);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'company_ids' => $user->companies()->pluck('companies.id'),
            ],
        ]);
    }

    // -------------------------
    // Current user
    // -------------------------
    public function me()
    {
        $user = auth()->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'company_ids' => $user->companies()->pluck('companies.id'),
        ]);
    }

    // -------------------------
    // Logout
    // -------------------------
    public function logout()
    {
        // Get user before invalidating token
        $user = auth()->user();

        // Log logout
        ActivityLog::create([
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'logged out',
            'module' => 'Authentication',
            'record_id' => $user->id,
            'old_data' => null,
            'new_data' => [
                'logout_time' => now()->format('Y-m-d H:i:s'),
            ],
        ]);

        JWTAuth::invalidate(JWTAuth::getToken());
        
        return response()->json(['message' => 'Logged out']);
    }

    // -------------------------
    // ADMIN: list users
    // -------------------------
    public function index()
    {
        return User::with('companies:id')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'role' => $u->role,
                'company_ids' => $u->companies->pluck('id'),
            ];
        });
    }

    public function users()
    {
        return $this->index(); // same shape, reuse it
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

        // Capture old data
        $oldData = [
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
        ];

        $user->update(['role' => $request->role]);

        // Log role update
        ActivityLog::create([
            'user_name' => auth()->user()->name,
            'user_role' => auth()->user()->role,
            'action' => 'updated role',
            'module' => 'User',
            'record_id' => $user->id,
            'old_data' => $oldData,
            'new_data' => [
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
            ],
        ]);

        return response()->json(['message' => 'Role updated', 'user' => $user]);
    }

    // -------------------------
    // ADMIN: update user's companies
    // -------------------------
    public function updateCompanies(Request $request, User $user)
    {
        $request->validate([
            'company_ids' => 'array',
            'company_ids.*' => 'exists:companies,id',
        ]);

        $user->companies()->sync($request->company_ids);

        ActivityLog::create([
            'user_name' => auth()->user()->name,
            'user_role' => auth()->user()->role,
            'action' => 'updated companies',
            'module' => 'User',
            'record_id' => $user->id,
            'old_data' => null,
            'new_data' => ['company_ids' => $request->company_ids],
        ]);

        return response()->json([
            'message' => 'Companies updated',
            'company_ids' => $user->companies()->pluck('companies.id'),
        ]);
    }

    public function refresh()
    {
        try {
            $newToken = auth()->refresh(true, true);

            return response()->json([
                'token' => $newToken
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Token cannot be refreshed'
            ], 401);
        }
    }

}