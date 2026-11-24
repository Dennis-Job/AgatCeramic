<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicCatalogRequest;
use App\Http\Resources\PublicCatalogBrandResource;
use App\Http\Resources\PublicCatalogCategoryResource;
use App\Http\Resources\PublicCatalogNavigationResource;
use App\Http\Resources\PublicCatalogProductResource;
use App\Queries\PublicCatalogNavigationQuery;
use App\Queries\PublicCatalogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function index(PublicCatalogRequest $request, PublicCatalogQuery $query, PublicCatalogNavigationQuery $navigation): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $brand = $filters['brand'] ?? null;
        $collectionSlug = $filters['collection_slug'] ?? null;
        $additional = [
            'categories' => PublicCatalogCategoryResource::collection($query->categories())->resolve($request),
            'brand_navigation' => PublicCatalogBrandResource::collection($query->brandNavigation())->resolve($request),
            'category_navigation' => (new PublicCatalogNavigationResource($navigation->forBrand(
                is_string($brand) ? $brand : null,
                is_string($collectionSlug) ? $collectionSlug : null,
            )))->resolve($request),
        ];
        if ($request->boolean('include_filters')) {
            $additional['filters'] = $query->filters($filters);
        }

        return PublicCatalogProductResource::collection($query->products($filters))->additional($additional);
    }
}
