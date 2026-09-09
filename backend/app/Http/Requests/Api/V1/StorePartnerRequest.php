<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\\+?[0-9()\\s-]{10,30}$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'website' => ['prohibited'],
            'type' => ['prohibited'],
            'source' => ['prohibited'],
            'status' => ['prohibited'],
            'assignee_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'phone', 'email', 'message'] as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
