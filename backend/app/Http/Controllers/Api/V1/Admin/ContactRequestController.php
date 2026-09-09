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
    ) {}

    public function index(ListContactRequestsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ContactRequest::class);
        $filters = $request->validated();
        $contacts = ContactRequest::query()->with('assignee:id,name')->latest()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('message', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['assignee_id'] ?? null, fn ($query, int $assigneeId) => $query->where('assignee_id', $assigneeId))
            ->when($request->boolean('unassigned'), fn ($query) => $query->whereNull('assignee_id'))
            ->paginate($filters['per_page'] ?? 25)->withQueryString();

        return ContactRequestResource::collection($contacts);
    }

    public function show(ContactRequest $contactRequest): ContactRequestResource
    {
        Gate::authorize('view', $contactRequest);

        return new ContactRequestResource($contactRequest->load('assignee:id,name'));
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
            $request->user(),
            $contactRequest,
            $request->enum('status', ContactRequestStatus::class),
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
            $request->user(), $contactRequest, $request->validated('body'),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
