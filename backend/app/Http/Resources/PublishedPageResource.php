<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Published snapshot projection, without draft fields. */
class PublishedPageResource extends HomePageResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        unset($data['site_layout']);

        return $data;
    }
}
