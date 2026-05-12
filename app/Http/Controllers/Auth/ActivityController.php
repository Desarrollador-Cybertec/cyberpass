<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::where('user_id', $request->user()->id)
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->query('start_date'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('end_date'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20);

        return response()->json(AuditLogResource::collection($logs)->response()->getData(true));
    }
}
