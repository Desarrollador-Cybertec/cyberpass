<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationCategoryResource;
use App\Services\IntegrationScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationCategoryController extends Controller
{
    public function __construct(private IntegrationScopeResolver $scopes) {}

    /**
     * Bóveda personal y categorías de la organización en una sola respuesta,
     * con discriminador `scope`, para que el cliente no tenga que conocer las
     * dos familias de rutas que usa el SPA.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $categories = $this->scopes->visibleCategories($user)->map(
            fn ($category) => new IntegrationCategoryResource(
                $category,
                $this->scopes->canCreateIn($user, $category),
            ),
        );

        return response()->json(['data' => $categories]);
    }
}
