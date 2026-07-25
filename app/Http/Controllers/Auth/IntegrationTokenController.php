<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreateIntegrationTokenRequest;
use App\Http\Resources\IntegrationTokenResource;
use App\Models\PersonalAccessToken;
use App\Services\IntegrationTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión de tokens de integración por parte del propio usuario.
 *
 * Vive en el grupo humano (sesión + TOTP), así que un token de integración no
 * puede emitir otros: EnsureSessionToken lo rechaza.
 */
class IntegrationTokenController extends Controller
{
    public function __construct(private IntegrationTokenService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->integrations()->latest('id')->get();

        return response()->json(['data' => IntegrationTokenResource::collection($tokens)]);
    }

    public function store(CreateIntegrationTokenRequest $request): JsonResponse
    {
        ['model' => $token, 'plain_text' => $plainText] = $this->service->issue(
            $request->user(),
            $request->validated(),
        );

        // El texto plano se devuelve UNA sola vez: en la BD solo queda su hash,
        // asi que ni el listado ni ninguna otra respuesta pueden recuperarlo.
        return response()
            ->json(
                (new IntegrationTokenResource($token))->toArray($request) + ['token' => $plainText],
                201,
            )
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, PersonalAccessToken $token): JsonResponse
    {
        $this->service->revoke($request->user(), $token);

        return response()->json(['message' => 'Token de integración revocado.']);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $revoked = $this->service->revokeAll($request->user());

        return response()->json([
            'message' => 'Se revocaron todos los tokens de integración.',
            'revoked' => $revoked,
        ]);
    }
}
