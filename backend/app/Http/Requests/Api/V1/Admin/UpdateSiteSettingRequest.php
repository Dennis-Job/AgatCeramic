<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\RussianPhoneNumber;
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
            'phones' => ['sometimes', 'array', 'list', 'max:1'],
            'phones.*' => ['required', 'string', 'regex:'.RussianPhoneNumber::PATTERN],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'partner_email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'notification_email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_bik' => ['sometimes', 'nullable', 'string', 'regex:/^\d{9}$/'],
            'bank_account' => ['sometimes', 'nullable', 'string', 'regex:/^\d{20}$/'],
            'bank_correspondent_account' => ['sometimes', 'nullable', 'string', 'regex:/^\d{20}$/'],
            'publish_bank_details' => ['sometimes', 'boolean'],
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $phones = $this->input('phones');
        if (is_array($phones)) {
            $this->merge(['phones' => array_map(
                static fn (mixed $phone): mixed => RussianPhoneNumber::normalize($phone) ?? $phone,
                $phones,
            )]);
        }
    }
}
