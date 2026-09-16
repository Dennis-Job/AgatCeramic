<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ContactRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListContactRequestActivityRequest;
use App\Http\Requests\Api\V1\Admin\ListContactRequestsRequest;
use App\Http\Requests\Api\V1\Admin\StoreContactRequestCommentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateContactRequestStatusRequest;
use App\Http\Resources\ContactRequestCommentResource;
use App\Http\Resources\ContactRequestResource;
use App\Http\Resources\ContactRequestStatusHistoryResource;
use App\Models\ContactRequest;
use App\Queries\ContactRequestQuery;
use App\Services\ContactCommentService;
use App\Services\ContactStatusManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ContactRequestController extends Controller
{
    public function __construct(
        private readonly ContactCommentService $commentService,
        private readonly ContactStatusManagementService $statusManagementService,
        private readonly ContactRequestQuery $contactRequests,
    ) {}

    public function index(ListContactRequestsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ContactRequest::class);

        return ContactRequestResource::collection(
            $this->contactRequests->paginate($request->validated(), $request->integer('per_page', 25)),
        );
    }

    public function show(ContactRequest $contactRequest): ContactRequestResource
    {
        Gate::authorize('view', $contactRequest);

        return new ContactRequestResource($this->contactRequests->prepare($contactRequest));
    }

    public function statuses(): JsonResponse
    {
        Gate::authorize('viewAny', ContactRequest::class);

        return response()->json(['data' => array_map(static fn (ContactRequestStatus $status): array => [
            'code' => $status->value,
            'name' => $status->label(),
            'is_terminal' => $status->isTerminal(),
        ], ContactRequestStatus::cases())]);
    }

    public function updateStatus(UpdateContactRequestStatusRequest $request, ContactRequest $contactRequest): ContactRequestResource
    {
        Gate::authorize('update', $contactRequest);

        return new ContactRequestResource($this->statusManagementService->update(
            $this->authenticatedAdmin($request),
            $contactRequest,
            $request->enum('status', ContactRequestStatus::class) ?? throw new \LogicException('Validated contact status is missing.'),
        )->load('assignee:id,name'));
    }

    public function statusHistory(ListContactRequestActivityRequest $request, ContactRequest $contactRequest): AnonymousResourceCollection
    {
        Gate::authorize('view', $contactRequest);

        return ContactRequestStatusHistoryResource::collection(
            $contactRequest->statusHistory()->orderBy('occurred_at')->orderBy('id')
                ->paginate($request->integer('per_page', 25))->withQueryString(),
        );
    }

    public function comments(ListContactRequestActivityRequest $request, ContactRequest $contactRequest): AnonymousResourceCollection
    {
        Gate::authorize('view', $contactRequest);

        return ContactRequestCommentResource::collection(
            $contactRequest->comments()->orderBy('created_at')->orderBy('id')
                ->paginate($request->integer('per_page', 25))->withQueryString(),
        );
    }

    public function storeComment(StoreContactRequestCommentRequest $request, ContactRequest $contactRequest): JsonResponse
    {
        Gate::authorize('createComment', $contactRequest);

        return (new ContactRequestCommentResource($this->commentService->add(
            $this->authenticatedAdmin($request), $contactRequest, $request->string('body')->toString(),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
