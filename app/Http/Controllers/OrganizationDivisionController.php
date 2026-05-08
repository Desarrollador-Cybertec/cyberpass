<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\CreateDivisionRequest;
use App\Http\Requests\Organization\UpdateDivisionRequest;
use App\Http\Resources\DivisionResource;
use App\Models\Division;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationDivisionController extends Controller
{
    public function __construct(
        private OrganizationService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('manageDivisions', $organization);

        $divisions = $organization->divisions()->latest()->get();

        return response()->json(DivisionResource::collection($divisions));
    }

    public function store(CreateDivisionRequest $request, Organization $organization): JsonResponse
    {
        $division = $this->service->createDivision($organization, $request->validated());

        $this->audit->log($request->user(), 'create', Division::class, $division->id, [
            'organization_id' => $organization->id,
        ]);

        return response()->json(new DivisionResource($division), 201);
    }

    public function show(Request $request, Organization $organization, Division $division): JsonResponse
    {
        $this->authorize('manageDivisions', $organization);
        abort_if($division->organization_id !== $organization->id, 404);

        return response()->json(new DivisionResource($division));
    }

    public function update(UpdateDivisionRequest $request, Organization $organization, Division $division): JsonResponse
    {
        abort_if($division->organization_id !== $organization->id, 404);

        $division = $this->service->updateDivision($division, $request->validated());

        $this->audit->log($request->user(), 'update', Division::class, $division->id);

        return response()->json(new DivisionResource($division));
    }

    public function destroy(Request $request, Organization $organization, Division $division): JsonResponse
    {
        $this->authorize('manageDivisions', $organization);
        abort_if($division->organization_id !== $organization->id, 404);

        $this->service->deleteDivision($division);

        $this->audit->log($request->user(), 'delete', Division::class, $division->id);

        return response()->json(['message' => 'División eliminada.']);
    }
}
