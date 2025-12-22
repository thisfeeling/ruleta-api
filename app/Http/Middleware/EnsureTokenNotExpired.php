<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTokenNotExpired
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if ($token && $token->expires_at && $token->expires_at->isPast()) {
            // Revoke the token
            $token->delete();

            return response()->json(['message' => 'Token expired'], 401);
        }

        return $next($request);
    }
}
