<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomePageResource;
use App\Queries\SiteAppearanceQuery;

class SiteAppearanceController extends Controller
{
    public function show(SiteAppearanceQuery $query): HomePageResource
    {
        return new HomePageResource($query->get());
    }
}
