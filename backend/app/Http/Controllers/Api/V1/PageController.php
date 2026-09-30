<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublishedPageResource;
use App\Queries\PublishedPageQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PageController extends Controller
{
    public function __construct(private readonly PublishedPageQuery $pages) {}

    public function index(): AnonymousResourceCollection
    {
        return PublishedPageResource::collection($this->pages->listing());
    }

    public function show(string $slug): PublishedPageResource
    {
        return new PublishedPageResource($this->pages->get($slug));
    }
}
