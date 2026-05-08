<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\CreateOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::withCount('users')
            ->with('domains')
            ->latest()
            ->paginate(20);

        return response()->json(OrganizationResource::collection($organizations)->response()->getData(true));
    }

    public function store(CreateOrganizationRequest $request): JsonResponse
    {
        $organization = $this->service->create($request->validated());

        $this->audit->log($request->user(), 'create', Organization::class, $organization->id);

        return response()->json(new OrganizationResource($organization->load('domains')), 201);
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json(
            new OrganizationResource($organization->loadCount('users')->load('domains'))
        );
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $organization = $this->service->update($organization, $request->validated());

        $this->audit->log($request->user(), 'update', Organization::class, $organization->id);

        return response()->json(new OrganizationResource($organization->load('domains')));
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        $this->audit->log($request->user(), 'delete', Organization::class, $organization->id);
        $this->service->delete($organization);

        return response()->json(['message' => 'Organización eliminada.']);
    }
}
