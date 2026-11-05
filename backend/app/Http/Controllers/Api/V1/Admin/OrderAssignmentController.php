<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignOrderRequest;
use App\Models\Order;
use App\Queries\OrderAssigneeQuery;
use App\Services\OrderAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrderAssignmentController extends Controller
{
    public function __construct(
        private readonly OrderAssignmentService $assignmentService,
        private readonly OrderAssigneeQuery $assignees,
    ) {}

    public function update(AssignOrderRequest $request, Order $order): Response
    {
        Gate::authorize('assign', $order);

        /** @var int|numeric-string|null $assigneeId */
        $assigneeId = $request->validated('assignee_id');
        $this->assignmentService->assign(
            $this->authenticatedAdmin($request),
            $order,
            $assigneeId === null ? null : (int) $assigneeId,
        );

        return response()->noContent();
    }

    public function candidates(): JsonResponse
    {
        Gate::authorize('viewAssignees', Order::class);

        return response()->json(['data' => $this->assignees->get()]);
    }
}
