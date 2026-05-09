<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateImageRequest;
use App\Http\Requests\UpdateImageRequest;
use App\Http\Resources\ImageResource;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Image::class);

        $images = Image::when(
            $request->query('name'),
            fn ($q, $name) => $q->where('name', 'like', "%{$name}%")
        )
            ->orderBy('name')
            ->paginate(50);

        return response()->json(ImageResource::collection($images)->response()->getData(true));
    }

    public function store(CreateImageRequest $request): JsonResponse
    {
        $image = Image::create($request->validated());

        return response()->json(new ImageResource($image), 201);
    }

    public function show(Request $request, Image $image): JsonResponse
    {
        $this->authorize('view', $image);

        return response()->json(new ImageResource($image));
    }

    public function update(UpdateImageRequest $request, Image $image): JsonResponse
    {
        $image->update($request->validated());

        return response()->json(new ImageResource($image->fresh()));
    }

    public function destroy(Request $request, Image $image): JsonResponse
    {
        $this->authorize('delete', $image);

        $image->delete();

        return response()->json(['message' => 'Imagen eliminada.']);
    }
}
