<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'payment_amount' => ['nullable', 'required_if:payment_status,paid,partially_paid,refunded', 'prohibited_if:payment_status,not_paid,pending', 'string', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/'],
            'payment_method' => ['nullable', 'required_if:payment_status,paid,partially_paid,refunded', 'prohibited_if:payment_status,not_paid,pending', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'prohibited_if:payment_status,not_paid,pending', 'string', 'max:255'],
            'paid_at' => ['nullable', 'required_if:payment_status,paid,partially_paid,refunded', 'prohibited_if:payment_status,not_paid,pending', 'date', 'before_or_equal:now'],
            'status' => ['prohibited'],
            'total_amount' => ['prohibited'],
            'order_number' => ['prohibited'],
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        foreach (['payment_method', 'payment_reference'] as $field) {
            if ($this->has($field)) {
                $value = trim($this->string($field)->toString());
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
