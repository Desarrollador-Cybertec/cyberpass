<?php

namespace App\Http\Controllers;

use App\Http\Requests\Asset\CreateCredentialRequest;
use App\Http\Requests\Asset\UpdateCredentialRequest;
use App\Http\Resources\CredentialResource;
use App\Models\Category;
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

    public function index(Category $category): JsonResponse
    {
        $this->authorize('viewAny', [Credential::class, $category]);

        $credentials = $category->credentials()->latest()->paginate(20);

        return response()->json(CredentialResource::collection($credentials)->response()->getData(true));
    }

    public function store(CreateCredentialRequest $request, Category $category): JsonResponse
    {
        $credential = $this->service->create($category, $category->organization, $request->user(), $request->validated());

        $this->audit->log($request->user(), 'create', Credential::class, $credential->id, [
            'category_id' => $category->id,
        ]);

        return response()->json(new CredentialResource($credential), 201);
    }

    public function show(Request $request, Category $category, Credential $credential): JsonResponse
    {
        $this->authorize('view', $credential);
        abort_if($credential->category_id !== $category->id, 404);

        $credential->password_plain = $this->service->decrypt($credential);
        $credential->notes_plain    = $this->service->decryptNotes($credential);

        $this->audit->log($request->user(), 'view', Credential::class, $credential->id);

        return response()->json(new CredentialResource($credential));
    }

    public function update(UpdateCredentialRequest $request, Category $category, Credential $credential): JsonResponse
    {
        abort_if($credential->category_id !== $category->id, 404);

        $credential = $this->service->update($credential, $request->validated());

        $this->audit->log($request->user(), 'update', Credential::class, $credential->id);

        return response()->json(new CredentialResource($credential));
    }

    public function destroy(Request $request, Category $category, Credential $credential): JsonResponse
    {
        $this->authorize('delete', $credential);
        abort_if($credential->category_id !== $category->id, 404);

        $this->service->delete($credential);

        $this->audit->log($request->user(), 'delete', Credential::class, $credential->id);

        return response()->json(['message' => 'Credencial eliminada.']);
    }
}
