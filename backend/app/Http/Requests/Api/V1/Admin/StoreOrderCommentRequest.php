<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        if ($this->has('body')) {
            $body = trim($this->string('body')->toString());
            $this->merge(['body' => $body === '' ? null : $body]);
        }
    }
}
