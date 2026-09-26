<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SliderResource;
use App\Models\Slider;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SliderController extends Controller
{
    public function show(string $slug): SliderResource
    {
        $slider = Slider::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();
        $slider->load(['banners' => static fn (BelongsToMany $query): BelongsToMany => $query->where('is_published', true)]);

        return new SliderResource($slider);
    }
}
