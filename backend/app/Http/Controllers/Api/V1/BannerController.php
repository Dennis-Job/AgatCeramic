<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BannerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BannerResource::collection(Banner::query()->where('is_published', true)->orderBy('id')->paginate(25));
    }
}
