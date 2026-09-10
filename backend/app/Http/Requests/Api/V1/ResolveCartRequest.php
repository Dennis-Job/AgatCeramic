<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ResolveCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'cart_token' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cart_token' => $this->header('X-Cart-Token'),
        ]);
    }

    public function cartToken(): ?string
    {
        return $this->string('cart_token')->toString() ?: null;
    }
}
