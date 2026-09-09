<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @mixin TModel
 */
abstract class ApiResource extends JsonResource
{
    /**
     * Keep the API response envelope explicit and stable for every resource.
     *
     * @var string|null
     */
    public static $wrap = 'data';
}
