<?php

namespace App\Http\Controllers\Integration;

use App\Helpers\IntegrationAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\StoreIntegrationCredentialRequest;
use App\Http\Requests\Integration\UpdateIntegrationCredentialRequest;
use App\Http\Resources\IntegrationCredentialResource;
use App\Models\Category;
use App\Models\Credential;
use App\Services\AuditService;
use App\Services\CredentialService;
use App\Services\IntegrationScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Escritura y lectura de credenciales para clientes de integración.
 *
 * La categoría NO va en la ruta, a diferencia de las dos familias de URL que usa
 * el SPA: así el cliente guarda solo {credential_id} y no tiene que recordar en
 * cuál de las dos vive cada credencial.
 */
class IntegrationCredentialController extends Controller
{
    public function __construct(
        private CredentialService $credentials,
        private IntegrationScopeResolver $scopes,
        private AuditService $audit,
    ) {}

    public function store(StoreIntegrationCredentialRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $category = Category::findOrFail($data['category_id']);

        $this->scopes->assertCanCreateIn($user, $category, $data['scope']);

        $credential = $data['scope'] === IntegrationScopeResolver::SCOPE_PERSONAL
            ? $this->credentials->createPersonal($category, $user, $data)
            // $category->organization no puede ser null aquí: assertCanCreateIn
            // ya rechazó cualquier categoría sin organization_id en esta rama.
            : $this->credentials->create($category, $category->organization, $user, $data);

        $this->audit->log(
            $user,
            'create',
            Credential::class,
            $credential->id,
            $this->auditMetadata($request, IntegrationAbility::CREDENTIALS_CREATE, [
                'scope'       => $data['scope'],
                'category_id' => $category->id,
            ]),
        );

        return response()->json(new IntegrationCredentialResource($credential->load('category')), 201);
    }

    public function show(Request $request, Credential $credential): JsonResponse
    {
        $this->scopes->assertCan($request->user(), $credential, 'view');

        $this->audit->log(
            $request->user(),
            'view',
            Credential::class,
            $credential->id,
            $this->auditMetadata($request, IntegrationAbility::CREDENTIALS_READ),
        );

        return response()->json(new IntegrationCredentialResource($credential->load('category')));
    }

    public function update(UpdateIntegrationCredentialRequest $request, Credential $credential): JsonResponse
    {
        $this->scopes->assertCan($request->user(), $credential, 'update');

        $credential = $this->credentials->update($credential, $request->validated(), $request->user());

        $this->audit->log(
            $request->user(),
            'update',
            Credential::class,
            $credential->id,
            $this->auditMetadata($request, IntegrationAbility::CREDENTIALS_UPDATE),
        );

        return response()->json(new IntegrationCredentialResource($credential->load('category')));
    }

    /** El único endpoint que devuelve el secreto en claro. */
    public function reveal(Request $request, Credential $credential): JsonResponse
    {
        $user = $request->user();

        $this->scopes->assertCan($user, $credential, 'view');

        // Más estricto que la política: solo credenciales creadas por el dueño
        // del token. Sin esto, y dado que CredentialPolicy::view() alcanza a
        // toda la organización, la integración se convertiría en un proxy de
        // lectura de la bóveda entera.
        abort_unless($credential->created_by === $user->id, 404);

        $this->audit->log(
            $user,
            'reveal_password',
            Credential::class,
            $credential->id,
            $this->auditMetadata($request, IntegrationAbility::CREDENTIALS_REVEAL),
        );

        return response()
            ->json([
                'password' => $this->credentials->decrypt($credential),
                'url'      => $credential->url,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function destroy(Request $request, Credential $credential): JsonResponse
    {
        $this->scopes->assertCan($request->user(), $credential, 'delete');

        $this->credentials->delete($credential);

        $this->audit->log(
            $request->user(),
            'delete',
            Credential::class,
            $credential->id,
            $this->auditMetadata($request, IntegrationAbility::CREDENTIALS_DELETE),
        );

        return response()->json(['message' => 'Credencial eliminada.']);
    }

    /**
     * Procedencia para la auditoría. `source` se fija en servidor; `client`
     * viene de una cabecera pero se valida contra una lista blanca, para que el
     * portador de un token no pueda falsear de dónde vinieron sus acciones.
     */
    private function auditMetadata(Request $request, string $ability, array $extra = []): array
    {
        $token = $request->user()->currentAccessToken();
        $client = $request->header('X-Integration-Client');

        return array_merge([
            'source'     => 'integration',
            'client'     => in_array($client, config('integrations.clients', []), true) ? $client : null,
            'token_id'   => $token?->id,
            'token_name' => $token?->name,
            'ability'    => $ability,
        ], $extra);
    }
}
