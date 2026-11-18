<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicBrandListRequest;
use App\Http\Resources\PublicBrandResource;
use App\Queries\PublicBrandQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function index(PublicBrandListRequest $request, PublicBrandQuery $brands): AnonymousResourceCollection
    {
        return PublicBrandResource::collection($brands->listing());
    }

    public function show(string $slug, PublicBrandQuery $brands): PublicBrandResource
    {
        return new PublicBrandResource($brands->get($slug));
    }
}
