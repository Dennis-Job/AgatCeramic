<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignContactRequestRequest;
use App\Models\ContactRequest;
use App\Services\ContactAssignmentService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ContactAssignmentController extends Controller
{
    public function __construct(private readonly ContactAssignmentService $contactAssignmentService) {}

    public function update(AssignContactRequestRequest $request, ContactRequest $contactRequest): Response
    {
        Gate::authorize('assign', $contactRequest);

        $assigneeId = $request->validated('assignee_id');

        $this->contactAssignmentService->assign(
            $this->authenticatedAdmin($request),
            $contactRequest,
            is_int($assigneeId) ? $assigneeId : null,
        );

        return response()->noContent();
    }
}
