<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCallbackRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^\\+?[0-9()\\s-]{10,30}$/'],
            'website' => ['prohibited'],
            'type' => ['prohibited'],
            'source' => ['prohibited'],
            'email' => ['prohibited'],
            'message' => ['prohibited'],
            'status' => ['prohibited'],
            'assignee_id' => ['prohibited'],
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        foreach (['name', 'phone'] as $field) {
            if ($this->has($field)) {
                $value = trim($this->string($field)->toString());
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    /** @return array{name?: string|null, phone: string} */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString() ?: null,
            'phone' => $this->string('phone')->toString(),
        ];
    }
}
