<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultCategoryController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $categories = Category::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json(CategoryResource::collection($categories)->response()->getData(true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'image_id'    => ['sometimes', 'nullable', 'exists:images,id'],
        ]);

        $category = Category::create([
            'user_id'     => $request->user()->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'image_id'    => $data['image_id'] ?? null,
        ]);

        $this->audit->log($request->user(), 'create', Category::class, $category->id, ['vault' => true]);

        return response()->json(new CategoryResource($category), 201);
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403);

        return response()->json(new CategoryResource($category));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'image_id'    => ['sometimes', 'nullable', 'exists:images,id'],
        ]);

        $category->update($data);

        $this->audit->log($request->user(), 'update', Category::class, $category->id, ['vault' => true]);

        return response()->json(new CategoryResource($category));
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403);

        $category->delete();

        $this->audit->log($request->user(), 'delete', Category::class, $category->id, ['vault' => true]);

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
