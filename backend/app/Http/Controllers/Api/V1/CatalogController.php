<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicCatalogCategoryResource;
use App\Http\Resources\PublicCatalogProductResource;
use App\Queries\PublicCatalogQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function index(Request $request, PublicCatalogQuery $query): AnonymousResourceCollection
    {
        return PublicCatalogProductResource::collection($query->products())->additional([
            'categories' => PublicCatalogCategoryResource::collection($query->categories())->resolve($request),
        ]);
    }
}
