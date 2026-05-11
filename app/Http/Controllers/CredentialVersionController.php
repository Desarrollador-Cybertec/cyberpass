<?php

namespace App\Http\Controllers;

use App\Http\Resources\CredentialVersionResource;
use App\Models\Category;
use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Services\AuditService;
use App\Services\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialVersionController extends Controller
{
    public function __construct(
        private CredentialService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request, Category $category, Credential $credential): JsonResponse
    {
        $this->authorize('viewAny', [CredentialVersion::class, $credential]);
        abort_if($credential->category_id !== $category->id, 404);

        $versions = $credential->versions()
            ->with('changedBy')
            ->latest()
            ->paginate(20);

        return response()->json(CredentialVersionResource::collection($versions)->response()->getData(true));
    }

    public function reveal(Request $request, Category $category, Credential $credential, CredentialVersion $version): JsonResponse
    {
        $this->authorize('restore', $version);
        abort_if($credential->category_id !== $category->id, 404);
        abort_if($version->credential_id !== $credential->id, 404);

        $this->audit->log($request->user(), 'reveal_password', CredentialVersion::class, $version->id, [
            'credential_id' => $credential->id,
        ]);

        return response()->json([
            'password' => $this->service->decryptVersion($version),
        ]);
    }

    public function restore(Request $request, Category $category, Credential $credential, CredentialVersion $version): JsonResponse
    {
        $this->authorize('restore', $version);
        abort_if($credential->category_id !== $category->id, 404);
        abort_if($version->credential_id !== $credential->id, 404);

        $updated = $this->service->restoreVersion($credential, $version);

        $this->audit->log($request->user(), 'update', Credential::class, $credential->id, [
            'restored_version_id' => $version->id,
        ]);

        return response()->json([
            'message'    => 'Versión restaurada correctamente.',
            'credential' => new \App\Http\Resources\CredentialResource($updated),
        ]);
    }
}
