<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra el arbol de rutas existente a los tokens de integracion.
 *
 * Es el sello de la integracion: con una sola comprobacion, TODO lo que ya
 * existe queda fuera del alcance de un token de integracion, sin tener que
 * auditar politica por politica. Importa porque hoy:
 *
 *  - CredentialPolicy::view() deja a cualquier miembro de una organizacion
 *    revelar cualquier credencial de ella.
 *  - SharedAccessTokenPolicy::create() deja a cualquier org_user generar un
 *    enlace publico SIN autenticacion para cualquier credencial de su
 *    organizacion. Ese seria el peor abuso de un token filtrado.
 *
 * Un token de integracion solo puede llegar a /api/integration/*.
 */
class EnsureSessionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->isIntegration()) {
            return response()->json([
                'message'    => 'Los tokens de integración no pueden usar este endpoint.',
                'error_code' => 'session_token_required',
            ], 403);
        }

        return $next($request);
    }
}
