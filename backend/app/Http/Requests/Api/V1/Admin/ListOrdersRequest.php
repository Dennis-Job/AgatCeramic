<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50', 'exists:order_statuses,code', 'prohibits:statuses'],
            'statuses' => ['nullable', 'array', 'min:1', 'max:100'],
            'statuses.*' => ['required', 'string', 'max:50', 'distinct', 'exists:order_statuses,code'],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'unassigned' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
