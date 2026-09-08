<?php

namespace App\Http\Requests\Api\V1;

class StoreOrderRequest extends CartTokenRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^\\+?[0-9()\\s-]{10,30}$/'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'customer_comment' => ['nullable', 'string', 'max:2000'],
            'website' => ['prohibited'],
            'items' => ['prohibited'],
            'total_amount' => ['prohibited'],
            'order_number' => ['prohibited'],
            'status' => ['prohibited'],
            'payment_status' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        foreach (['customer_name', 'customer_phone', 'customer_email', 'delivery_address', 'customer_comment'] as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
