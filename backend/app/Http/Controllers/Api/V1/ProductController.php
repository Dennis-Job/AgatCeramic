<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProductDetailResource;
use App\Queries\PublicProductQuery;

class ProductController extends Controller
{
    public function show(string $slug, PublicProductQuery $query): PublicProductDetailResource
    {
        return new PublicProductDetailResource($query->find($slug));
    }
}
