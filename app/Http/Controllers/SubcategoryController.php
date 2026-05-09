<?php

namespace App\Http\Controllers;

use App\Http\Requests\Asset\CreateSubcategoryRequest;
use App\Http\Requests\Asset\UpdateSubcategoryRequest;
use App\Http\Resources\SubcategoryResource;
use App\Models\Category;
use App\Models\Subcategory;
use App\Services\AssetService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    public function __construct(
        private AssetService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request, Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        $subcategories = $category->subcategories()->latest()->paginate(20);

        return response()->json(SubcategoryResource::collection($subcategories)->response()->getData(true));
    }

    public function store(CreateSubcategoryRequest $request, Category $category): JsonResponse
    {
        $subcategory = $this->service->createSubcategory($category, $request->validated());

        $this->audit->log($request->user(), 'create', Subcategory::class, $subcategory->id, [
            'category_id' => $category->id,
        ]);

        return response()->json(new SubcategoryResource($subcategory), 201);
    }

    public function show(Request $request, Category $category, Subcategory $subcategory): JsonResponse
    {
        $this->authorize('view', $category);
        abort_if($subcategory->category_id !== $category->id, 404);

        return response()->json(new SubcategoryResource($subcategory));
    }

    public function update(UpdateSubcategoryRequest $request, Category $category, Subcategory $subcategory): JsonResponse
    {
        abort_if($subcategory->category_id !== $category->id, 404);

        $subcategory = $this->service->updateSubcategory($subcategory, $request->validated());

        $this->audit->log($request->user(), 'update', Subcategory::class, $subcategory->id);

        return response()->json(new SubcategoryResource($subcategory));
    }

    public function destroy(Request $request, Category $category, Subcategory $subcategory): JsonResponse
    {
        $this->authorize('update', $category);
        abort_if($subcategory->category_id !== $category->id, 404);

        $this->service->deleteSubcategory($subcategory);

        $this->audit->log($request->user(), 'delete', Subcategory::class, $subcategory->id);

        return response()->json(['message' => 'Subcategoría eliminada.']);
    }
}
