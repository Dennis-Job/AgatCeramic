<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomePageResource extends JsonResource
{
    public static $wrap = 'data';

    /** @var array<string, mixed> */
    private readonly array $payload;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        parent::__construct($payload);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return $this->payload;
    }
}
