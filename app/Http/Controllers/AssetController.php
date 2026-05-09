<?php

namespace App\Http\Controllers;

use App\Http\Requests\Asset\CreateAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\Subcategory;
use App\Services\AssetService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(
        private AssetService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request, Subcategory $subcategory): JsonResponse
    {
        $this->authorize('view', $subcategory->category);

        $assets = $subcategory->assets()->latest()->paginate(20);

        return response()->json(AssetResource::collection($assets)->response()->getData(true));
    }

    public function store(CreateAssetRequest $request, Subcategory $subcategory): JsonResponse
    {
        $asset = $this->service->createAsset($subcategory, $subcategory->category->organization, $request->validated());

        $this->audit->log($request->user(), 'create', Asset::class, $asset->id, [
            'subcategory_id' => $subcategory->id,
        ]);

        return response()->json(new AssetResource($asset), 201);
    }

    public function show(Request $request, Subcategory $subcategory, Asset $asset): JsonResponse
    {
        $this->authorize('view', $subcategory->category);
        abort_if($asset->subcategory_id !== $subcategory->id, 404);

        return response()->json(new AssetResource($asset));
    }

    public function update(UpdateAssetRequest $request, Subcategory $subcategory, Asset $asset): JsonResponse
    {
        abort_if($asset->subcategory_id !== $subcategory->id, 404);

        $asset = $this->service->updateAsset($asset, $request->validated());

        $this->audit->log($request->user(), 'update', Asset::class, $asset->id);

        return response()->json(new AssetResource($asset));
    }

    public function destroy(Request $request, Subcategory $subcategory, Asset $asset): JsonResponse
    {
        $this->authorize('update', $subcategory->category);
        abort_if($asset->subcategory_id !== $subcategory->id, 404);

        $this->service->deleteAsset($asset);

        $this->audit->log($request->user(), 'delete', Asset::class, $asset->id);

        return response()->json(['message' => 'Asset eliminado.']);
    }
}
