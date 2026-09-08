<?php

namespace App\Http\Requests\Api\V1;

class StoreCartItemRequest extends CartTokenRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
