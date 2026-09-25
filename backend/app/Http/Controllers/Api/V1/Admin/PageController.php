<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePageRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Services\PageManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{
    public function __construct(private readonly PageManagementService $managementService) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Page::class);

        return PageResource::collection(Page::query()->orderByDesc('updated_at')->orderByDesc('id')->paginate(25));
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        Gate::authorize('create', Page::class);

        return (new PageResource($this->managementService->create($this->authenticatedAdmin($request), $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Page $page): PageResource
    {
        Gate::authorize('view', $page);

        return new PageResource($page);
    }

    public function update(UpdatePageRequest $request, Page $page): PageResource
    {
        Gate::authorize('update', $page);

        return new PageResource($this->managementService->update($this->authenticatedAdmin($request), $page, $request->validated()));
    }

    public function destroy(Request $request, Page $page): Response
    {
        Gate::authorize('delete', $page);
        $this->managementService->delete($this->authenticatedAdmin($request), $page);

        return response()->noContent();
    }
}
