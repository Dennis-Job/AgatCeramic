<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\LegalDocumentContentRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLegalDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        // Global TrimStrings would remove meaningful spaces between formatted text nodes.
        if ($this->isJson()) {
            $payload = json_decode($this->getContent(), true);
            if (is_array($payload) && array_key_exists('content', $payload)) {
                $this->merge(['content' => $payload['content']]);
            }
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type' => ['required', Rule::in(['offer', 'privacy_policy', 'consent'])],
            'version' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('legal_document_versions', 'version')->where('type', is_string($type) ? $type : '')],
            'body' => ['required', 'string', 'max:200000'],
            'content' => ['nullable', new LegalDocumentContentRule],
        ];
    }
}
