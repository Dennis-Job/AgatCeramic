<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'operator_type' => ['sometimes', 'nullable', Rule::in(['individual_entrepreneur', 'legal_entity'])],
            'seller_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'entrepreneur_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'inn' => ['sometimes', 'nullable', 'string', 'regex:/^(?:\d{10}|\d{12})$/'],
            'ogrnip' => ['sometimes', 'nullable', 'string', 'regex:/^\d{15}$/'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'phones' => ['sometimes', 'array', 'max:5'],
            'phones.*' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9()\-\s]{7,32}$/'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_bik' => ['sometimes', 'nullable', 'string', 'regex:/^\d{9}$/'],
            'bank_account' => ['sometimes', 'nullable', 'string', 'regex:/^\d{20}$/'],
            'bank_correspondent_account' => ['sometimes', 'nullable', 'string', 'regex:/^\d{20}$/'],
            'publish_bank_details' => ['sometimes', 'boolean'],
        ];
    }
}
