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

    #[\Override]
    protected function prepareForValidation(): void
    {
        foreach (['name', 'phone', 'email', 'message'] as $field) {
            if ($this->has($field)) {
                $value = trim($this->string($field)->toString());
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    /** @return array{name: string, phone: string, email: string, message: string} */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'phone' => $this->string('phone')->toString(),
            'email' => $this->string('email')->toString(),
            'message' => $this->string('message')->toString(),
        ];
    }
}
