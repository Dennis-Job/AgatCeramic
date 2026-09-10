<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower($this->string('email')->toString())]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:rfc', 'max:255']];
    }
}
