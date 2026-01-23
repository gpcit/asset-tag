<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        try {
            // Try to get the user from the JWT token
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // If roles are defined, check if the user's role is allowed
            if (!empty($roles) && !in_array($user->role, $roles)) {
                return response()->json([
                    'message' => 'Forbidden',
                    'your_role' => $user->role,
                    'allowed_roles' => $roles
                ], 403);
            }

        } catch (JWTException $e) {
            // Catch all JWT errors: token missing, expired, invalid
            return response()->json([
                'message' => 'Token invalid or missing',
                'error' => $e->getMessage()
            ], 401);
        }

        // Everything is fine → continue
        return $next($request);
    }
}
