<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnsurePersonalAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token || $token instanceof TransientToken) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        return $next($request);
    }
}
