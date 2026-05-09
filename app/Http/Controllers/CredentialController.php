<?php

namespace App\Http\Controllers;

use App\Http\Requests\Asset\CreateCredentialRequest;
use App\Http\Requests\Asset\UpdateCredentialRequest;
use App\Http\Resources\CredentialResource;
use App\Models\Asset;
use App\Models\Credential;
use App\Services\AuditService;
use App\Services\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    public function __construct(
        private CredentialService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('viewAny', [Credential::class, $asset]);

        $credentials = $asset->credentials()->latest()->paginate(20);

        return response()->json(CredentialResource::collection($credentials)->response()->getData(true));
    }

    public function store(CreateCredentialRequest $request, Asset $asset): JsonResponse
    {
        $credential = $this->service->create($asset, $asset->organization, $request->user(), $request->validated());

        $this->audit->log($request->user(), 'create', Credential::class, $credential->id, [
            'asset_id' => $asset->id,
        ]);

        return response()->json(new CredentialResource($credential), 201);
    }

    public function show(Request $request, Asset $asset, Credential $credential): JsonResponse
    {
        $this->authorize('view', $credential);
        abort_if($credential->asset_id !== $asset->id, 404);

        $credential->password_plain = $this->service->decrypt($credential);
        $credential->notes_plain    = $this->service->decryptNotes($credential);

        $this->audit->log($request->user(), 'view', Credential::class, $credential->id);

        return response()->json(new CredentialResource($credential));
    }

    public function update(UpdateCredentialRequest $request, Asset $asset, Credential $credential): JsonResponse
    {
        abort_if($credential->asset_id !== $asset->id, 404);

        $credential = $this->service->update($credential, $request->validated());

        $this->audit->log($request->user(), 'update', Credential::class, $credential->id);

        return response()->json(new CredentialResource($credential));
    }

    public function destroy(Request $request, Asset $asset, Credential $credential): JsonResponse
    {
        $this->authorize('delete', $credential);
        abort_if($credential->asset_id !== $asset->id, 404);

        $this->service->delete($credential);

        $this->audit->log($request->user(), 'delete', Credential::class, $credential->id);

        return response()->json(['message' => 'Credencial eliminada.']);
    }
}
