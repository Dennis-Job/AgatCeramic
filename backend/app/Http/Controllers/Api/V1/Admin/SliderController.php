<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSliderRequest;
use App\Http\Requests\Api\V1\Admin\UpdateSliderRequest;
use App\Http\Resources\BannerResource;
use App\Http\Resources\SliderResource;
use App\Models\Banner;
use App\Models\Slider;
use App\Services\SliderManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SliderController extends Controller
{
    public function __construct(private readonly SliderManagementService $managementService) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Slider::class);

        return SliderResource::collection(Slider::query()->with('banners')->orderByDesc('updated_at')->orderByDesc('id')->paginate(25));
    }

    public function bannerOptions(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Slider::class);
        $request->validate(['q' => ['sometimes', 'string', 'max:100']]);
        $search = $request->query('q');
        $banners = Banner::query()->orderByDesc('id');
        if (is_string($search) && $search !== '') {
            $banners->where('title', 'like', '%'.$search.'%');
        }

        return BannerResource::collection($banners->limit(20)->get());
    }

    public function store(StoreSliderRequest $request): JsonResponse
    {
        Gate::authorize('create', Slider::class);

        return (new SliderResource($this->managementService->create($this->authenticatedAdmin($request), $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Slider $slider): SliderResource
    {
        Gate::authorize('view', $slider);

        return new SliderResource($slider->load('banners'));
    }

    public function update(UpdateSliderRequest $request, Slider $slider): SliderResource
    {
        Gate::authorize('update', $slider);

        return new SliderResource($this->managementService->update($this->authenticatedAdmin($request), $slider, $request->validated()));
    }

    public function destroy(Request $request, Slider $slider): Response
    {
        Gate::authorize('delete', $slider);
        $this->managementService->delete($this->authenticatedAdmin($request), $slider);

        return response()->noContent();
    }
}
