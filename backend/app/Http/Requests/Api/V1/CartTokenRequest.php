<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CartTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'cart_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function cartToken(): string
    {
        return $this->validated('cart_token');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cart_token' => $this->header('X-Cart-Token'),
        ]);
    }
}
