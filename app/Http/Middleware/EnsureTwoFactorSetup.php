<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->two_factor_enabled) {
            return response()->json([
                'message'    => 'Debes configurar la verificación en dos pasos antes de continuar.',
                'error_code' => 'two_factor_setup_required',
            ], 403);
        }

        return $next($request);
    }
}
