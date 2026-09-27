<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreMediaRequest;
use App\Http\Requests\Api\V1\Admin\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    public function __construct(private readonly MediaManagementService $managementService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Media::class);
        $request->validate(['kind' => ['sometimes', 'in:image,document']]);
        $kind = $request->query('kind');
        $kind = is_string($kind) ? $kind : null;
        $media = Media::query()->when($kind, fn ($query) => $query->where('kind', $kind))
            ->orderByDesc('id')->paginate(25);

        return MediaResource::collection($media);
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        Gate::authorize('create', Media::class);
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'Выберите файл.']);
        }
        $alt = $request->input('alt');
        $attributes = [
            'kind' => $request->string('kind')->toString(),
            'title' => $request->string('title')->toString(),
            'alt' => is_string($alt) ? $alt : null,
        ];

        return (new MediaResource($this->managementService->create($this->authenticatedAdmin($request), $file, $attributes)))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Media $media): MediaResource
    {
        Gate::authorize('view', $media);

        return new MediaResource($media);
    }

    public function update(UpdateMediaRequest $request, Media $media): MediaResource
    {
        Gate::authorize('update', $media);

        return new MediaResource($this->managementService->update($this->authenticatedAdmin($request), $media, $request->validated()));
    }

    public function destroy(Request $request, Media $media): Response
    {
        Gate::authorize('delete', $media);
        $this->managementService->delete($this->authenticatedAdmin($request), $media);

        return response()->noContent();
    }
}
