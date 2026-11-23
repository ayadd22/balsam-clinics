<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', AuditLog::class);

        $auditLogs = AuditLog::query()
            ->with('user')
            ->latest('created_at')
            ->paginate(20);

        return AuditLogResource::collection($auditLogs);
    }

    public function show(AuditLog $auditLog)
    {
        $this->authorize('view', $auditLog);

        $auditLog->load('user');

        return new AuditLogResource($auditLog);
    }
}
