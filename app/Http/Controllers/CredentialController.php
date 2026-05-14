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

    public function index(Request $request, Category $category): JsonResponse
    {
        $this->authorize('viewAny', [Credential::class, $category]);

        $credentials = $category->credentials()
            ->when($request->query('q'), function ($q, $s) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);

                return $q->where('name', 'ILIKE', "%{$escaped}%")->orWhere('username', 'ILIKE', "%{$escaped}%");
            })
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest()
            ->paginate(20);

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

        $this->audit->log($request->user(), 'view', Credential::class, $credential->id);

        return response()->json(new CredentialResource($credential));
    }

    public function update(UpdateCredentialRequest $request, Category $category, Credential $credential): JsonResponse
    {
        abort_if($credential->category_id !== $category->id, 404);
        $this->authorize('update', $credential);

        $credential = $this->service->update($credential, $request->validated(), $request->user());

        $this->audit->log($request->user(), 'update', Credential::class, $credential->id);

        return response()->json(new CredentialResource($credential));
    }

    public function reveal(Request $request, Category $category, Credential $credential): JsonResponse
    {
        $this->authorize('view', $credential);
        abort_if($credential->category_id !== $category->id, 404);

        $this->audit->log($request->user(), 'reveal_password', Credential::class, $credential->id);

        return response()->json([
            'password' => $this->service->decrypt($credential),
            'notes'    => $this->service->decryptNotes($credential),
        ]);
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
