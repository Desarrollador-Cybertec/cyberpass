<?php

namespace App\Http\Controllers;

use App\Http\Requests\Asset\CreateCategoryRequest;
use App\Http\Requests\Asset\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Organization;
use App\Services\AssetService;
use App\Services\AuditService;
use App\Services\ReplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private AssetService $service,
        private AuditService $audit,
        private ReplicationService $replication,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', [Category::class, $organization]);

        $categories = $organization->categories()
            ->when($request->query('division_id'), fn ($q, $id) => $q->where('division_id', $id))
            ->latest()
            ->paginate(20);

        return response()->json(CategoryResource::collection($categories)->response()->getData(true));
    }

    public function store(CreateCategoryRequest $request, Organization $organization): JsonResponse
    {
        $data = $request->safe()->except('replicate_to_other_organizations');

        $category = $this->service->createCategory($organization, $data);

        $this->audit->log($request->user(), 'create', Category::class, $category->id, [
            'organization_id' => $organization->id,
        ]);

        $replication = null;

        if ($request->boolean('replicate_to_other_organizations') && $request->user()->can('replicate', Category::class)) {
            $replication = $this->replication->replicateCategory($category);

            $this->audit->log($request->user(), 'replicate', Category::class, $category->id, [
                'results' => $replication,
            ]);
        }

        return response()->json([
            'category'    => new CategoryResource($category),
            'replication' => $replication ?: null,
        ], 201);
    }

    public function show(Request $request, Organization $organization, Category $category): JsonResponse
    {
        $this->authorize('view', $category);
        abort_if($category->organization_id !== $organization->id, 404);

        return response()->json(new CategoryResource($category));
    }

    public function update(UpdateCategoryRequest $request, Organization $organization, Category $category): JsonResponse
    {
        $this->authorize('update', $category);
        abort_if($category->organization_id !== $organization->id, 404);

        $category = $this->service->updateCategory($category, $request->validated());

        $this->audit->log($request->user(), 'update', Category::class, $category->id);

        return response()->json(new CategoryResource($category));
    }

    public function destroy(Request $request, Organization $organization, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);
        abort_if($category->organization_id !== $organization->id, 404);

        $this->service->deleteCategory($category);

        $this->audit->log($request->user(), 'delete', Category::class, $category->id);

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
