<?php

namespace App\Http\Requests\Api\V1;

use App\Support\RussianPhoneNumber;

class StoreOrderRequest extends CartTokenRequest
{
    /** @return array<string, list<mixed>> */
    #[\Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:'.RussianPhoneNumber::PATTERN],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'customer_comment' => ['nullable', 'string', 'max:2000'],
            'website' => ['prohibited'],
            'items' => ['prohibited'],
            'total_amount' => ['prohibited'],
            'order_number' => ['prohibited'],
            'status' => ['prohibited'],
            'payment_status' => ['prohibited'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:255'],
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);

        foreach (['customer_name', 'customer_phone', 'customer_email', 'delivery_address', 'customer_comment'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->string($field)->toString());
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }

        if ($this->has('customer_phone')) {
            $this->merge(['customer_phone' => RussianPhoneNumber::normalize($this->input('customer_phone')) ?? $this->input('customer_phone')]);
        }
    }

    public function idempotencyKey(): string
    {
        return $this->string('idempotency_key')->toString();
    }

    /** @return array<string, mixed> */
    public function orderAttributes(): array
    {
        return [
            'customer_name' => $this->string('customer_name')->toString(),
            'customer_phone' => $this->string('customer_phone')->toString(),
            'customer_email' => $this->string('customer_email')->toString() ?: null,
            'delivery_address' => $this->string('delivery_address')->toString(),
            'customer_comment' => $this->string('customer_comment')->toString() ?: null,
        ];
    }
}
