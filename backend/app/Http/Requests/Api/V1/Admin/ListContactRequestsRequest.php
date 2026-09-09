<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ContactRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContactRequestsRequest extends FormRequest
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
            'type' => ['nullable', 'string', 'in:callback,email,partner'],
            'status' => ['nullable', Rule::enum(ContactRequestStatus::class)],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'unassigned' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
