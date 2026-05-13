<?php

namespace App\Http\Controllers;

use App\Http\Resources\CredentialResource;
use App\Models\Credential;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialSearchController extends Controller
{
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', [Credential::class, $organization->categories()->getModel()]);

        abort_unless(
            $request->user()->isSysAdmin() || $request->user()->organization_id === $organization->id,
            403
        );

        $credentials = Credential::with('image')
            ->where('organization_id', $organization->id)
            ->when($request->query('q'), function ($q, $s) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);

                return $q->where('name', 'ILIKE', "%{$escaped}%")->orWhere('username', 'ILIKE', "%{$escaped}%");
            })
            ->when($request->query('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->query('division_id'), fn ($q, $id) => $q->whereHas('category', fn ($c) => $c->where('division_id', $id)))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->latest()
            ->paginate(20);

        return response()->json(CredentialResource::collection($credentials)->response()->getData(true));
    }
}
