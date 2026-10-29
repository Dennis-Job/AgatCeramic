<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicCatalogRequest;
use App\Http\Resources\PublicCatalogCategoryResource;
use App\Http\Resources\PublicCatalogProductResource;
use App\Queries\PublicCatalogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function index(PublicCatalogRequest $request, PublicCatalogQuery $query): AnonymousResourceCollection
    {
        return PublicCatalogProductResource::collection($query->products($request->validated()))->additional([
            'categories' => PublicCatalogCategoryResource::collection($query->categories())->resolve($request),
        ]);
    }
}
