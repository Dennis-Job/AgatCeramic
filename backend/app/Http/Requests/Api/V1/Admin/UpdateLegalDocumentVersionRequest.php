<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\LegalDocumentVersion;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateLegalDocumentVersionRequest extends StoreLegalDocumentVersionRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Gate::allows('update', SiteSetting::class);
    }

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function rules(): array
    {
        /** @var LegalDocumentVersion $document */
        $document = $this->route('legalDocumentVersion');
        $rules = parent::rules();
        $rules['type'] = ['sometimes', Rule::in([$document->type])];
        $rules['version'] = ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('legal_document_versions', 'version')->where('type', $document->type)->ignore($document->id)];

        return $rules;
    }
}
