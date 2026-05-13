<?php

namespace App\Http\Controllers;

use App\Http\Resources\CredentialResource;
use App\Models\Category;
use App\Models\Credential;
use App\Services\AuditService;
use App\Services\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VaultCredentialController extends Controller
{
    public function __construct(
        private CredentialService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403);

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

    public function store(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:5000'],
            'notes'    => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type'     => ['sometimes', Rule::in(['password', 'api_key', 'ssh', 'certificate', 'other'])],
            'image_id' => ['sometimes', 'nullable', 'integer', 'exists:images,id'],
        ]);

        $credential = $this->service->createPersonal($category, $request->user(), $data);

        $this->audit->log($request->user(), 'create', Credential::class, $credential->id, ['vault' => true]);

        return response()->json(new CredentialResource($credential), 201);
    }

    public function show(Request $request, Category $category, Credential $credential): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403);
        abort_if($credential->category_id !== $category->id, 404);

        $this->audit->log($request->user(), 'view', Credential::class, $credential->id);

        return response()->json(new CredentialResource($credential));
    }

    public function update(Request $request, Category $category, Credential $credential): JsonResponse
    {
        abort_if($credential->user_id !== $request->user()->id, 403);
        abort_if($credential->category_id !== $category->id, 404);

        $data = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'max:5000'],
            'notes'    => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type'     => ['sometimes', Rule::in(['password', 'api_key', 'ssh', 'certificate', 'other'])],
            'image_id' => ['sometimes', 'nullable', 'integer', 'exists:images,id'],
        ]);

        $credential = $this->service->update($credential, $data);

        $this->audit->log($request->user(), 'update', Credential::class, $credential->id);

        return response()->json(new CredentialResource($credential));
    }

    public function destroy(Request $request, Category $category, Credential $credential): JsonResponse
    {
        abort_if($credential->user_id !== $request->user()->id, 403);
        abort_if($credential->category_id !== $category->id, 404);

        $this->service->delete($credential);

        $this->audit->log($request->user(), 'delete', Credential::class, $credential->id);

        return response()->json(['message' => 'Credencial eliminada.']);
    }

    public function reveal(Request $request, Category $category, Credential $credential): JsonResponse
    {
        abort_if($credential->user_id !== $request->user()->id, 403);
        abort_if($credential->category_id !== $category->id, 404);

        $this->audit->log($request->user(), 'reveal_password', Credential::class, $credential->id);

        return response()->json([
            'password' => $this->service->decrypt($credential),
            'notes'    => $this->service->decryptNotes($credential),
        ]);
    }
}
