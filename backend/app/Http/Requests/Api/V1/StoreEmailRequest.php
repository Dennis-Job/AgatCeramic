<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'website' => ['prohibited'],
            'type' => ['prohibited'],
            'source' => ['prohibited'],
            'phone' => ['prohibited'],
            'status' => ['prohibited'],
            'assignee_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'email', 'message'] as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
