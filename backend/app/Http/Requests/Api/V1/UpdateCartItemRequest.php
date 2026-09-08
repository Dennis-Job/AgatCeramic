<?php

namespace App\Http\Requests\Api\V1;

class UpdateCartItemRequest extends CartTokenRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
