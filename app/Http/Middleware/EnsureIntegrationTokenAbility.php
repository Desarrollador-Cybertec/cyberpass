<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta del grupo /api/integration/*: exige token de integración y permisos.
 *
 * No se usan los middleware que trae Sanctum (CheckAbilities) porque lanzan una
 * excepción que el renderer global aplana a un 403 genérico sin código, y un
 * cliente máquina necesita distinguir "falta este permiso" de "esto no es tuyo"
 * para saber si pedirle al usuario un token nuevo o mostrarle otro error.
 */
class EnsureIntegrationTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! $token->isIntegration()) {
            return response()->json([
                'message'    => 'Este endpoint requiere un token de integración.',
                'error_code' => 'integration_token_required',
            ], 403);
        }

        $granted = (array) $token->abilities;

        foreach ($abilities as $ability) {
            // in_array y no $token->can(): can() trata '*' como universal, y
            // esto tiene que seguir negando aunque un bug futuro llegara a
            // emitir un token de integración con comodín.
            if (! in_array($ability, $granted, true)) {
                return response()->json([
                    'message'    => 'El token de integración no tiene el permiso requerido.',
                    'error_code' => 'integration_ability_missing',
                    'ability'    => $ability,
                ], 403);
            }
        }

        return $next($request);
    }
}
