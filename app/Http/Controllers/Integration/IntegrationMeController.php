<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identidad a la que apunta el token. Axis lo llama en cuanto el usuario pega
 * el token, para confirmar que es suyo y mostrar "conectado como ...".
 */
class IntegrationMeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('organization');
        $token = $request->user()->currentAccessToken();

        return response()->json([
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'role'         => $user->role,
                'account_type' => $user->account_type,
            ],
            'organization' => $user->organization ? [
                'id'   => $user->organization->id,
                'name' => $user->organization->name,
                'slug' => $user->organization->slug,
            ] : null,
            'token' => [
                'id'           => $token->id,
                'name'         => $token->name,
                'abilities'    => (array) $token->abilities,
                'expires_at'   => $token->expires_at,
                'last_used_at' => $token->last_used_at,
            ],
        ]);
    }
}
