<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomePageResource;
use App\Models\Page;
use App\Queries\ContentPreviewQuery;
use Illuminate\Support\Facades\Gate;

class ContentPreviewController extends Controller
{
    public function __invoke(string $slug, ContentPreviewQuery $query): HomePageResource
    {
        Gate::authorize('viewAny', Page::class);

        return new HomePageResource($query->get($slug));
    }
}
