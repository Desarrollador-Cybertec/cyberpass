<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\CreateDomainRequest;
use App\Http\Resources\OrganizationDomainResource;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationDomainController extends Controller
{
    public function __construct(private OrganizationService $service) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('manageDomains', $organization);

        return response()->json(
            OrganizationDomainResource::collection($organization->domains)
        );
    }

    public function store(CreateDomainRequest $request, Organization $organization): JsonResponse
    {
        $domain = $this->service->addDomain($organization, $request->validated('domain'));

        return response()->json(new OrganizationDomainResource($domain), 201);
    }

    public function destroy(Request $request, Organization $organization, OrganizationDomain $domain): JsonResponse
    {
        $this->authorize('manageDomains', $organization);

        abort_if($domain->organization_id !== $organization->id, 404);

        $this->service->removeDomain($domain);

        return response()->json(['message' => 'Dominio eliminado.']);
    }
}
