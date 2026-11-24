<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicCollectionListRequest;
use App\Http\Resources\PublicCollectionResource;
use App\Queries\PublicCollectionQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionController extends Controller
{
    public function index(PublicCollectionListRequest $request, PublicCollectionQuery $collections): AnonymousResourceCollection
    {
        return PublicCollectionResource::collection($collections->listing());
    }

    public function show(string $brand, string $slug, PublicCollectionQuery $collections): PublicCollectionResource
    {
        return new PublicCollectionResource($collections->get($brand, $slug));
    }
}
