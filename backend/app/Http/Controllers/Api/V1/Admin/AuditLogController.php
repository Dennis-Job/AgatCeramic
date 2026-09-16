<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Queries\AuditLogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogQuery $auditLogs) {}

    public function index(ListAuditLogsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AuditLog::class);

        return AuditLogResource::collection(
            $this->auditLogs->paginate($request->validated(), $request->integer('per_page', 25)),
        );
    }

    public function show(AuditLog $auditLog): AuditLogResource
    {
        Gate::authorize('view', $auditLog);

        return new AuditLogResource($this->auditLogs->prepare($auditLog));
    }
}
