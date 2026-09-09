<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ContactRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ContactRequestStatus::class)]];
    }
}
