<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomePageResource;
use App\Queries\HomePageContentQuery;

class HomePageController extends Controller
{
    public function show(HomePageContentQuery $query): HomePageResource
    {
        return new HomePageResource($query->get());
    }
}
