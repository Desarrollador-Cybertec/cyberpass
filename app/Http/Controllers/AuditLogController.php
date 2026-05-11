<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $user = $request->user();

        $logs = AuditLog::with(['user', 'organization'])
            ->when(! $user->isSysAdmin(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->query('organization_id') && $user->isSysAdmin(), fn ($q, $id) => $q->where('organization_id', $id))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('action'), fn ($q, $v) => $q->where('action', $v))
            ->when($request->query('entity_type'), fn ($q, $v) => $q->where('entity_type', $v))
            ->when($request->query('start_date'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('end_date'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(20);

        return response()->json(AuditLogResource::collection($logs)->response()->getData(true));
    }

    public function forOrganization(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewOrganization', [AuditLog::class, $organization]);

        $logs = AuditLog::with(['user', 'organization'])
            ->where('organization_id', $organization->id)
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('action'), fn ($q, $v) => $q->where('action', $v))
            ->when($request->query('entity_type'), fn ($q, $v) => $q->where('entity_type', $v))
            ->when($request->query('start_date'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('end_date'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(20);

        return response()->json(AuditLogResource::collection($logs)->response()->getData(true));
    }
}
