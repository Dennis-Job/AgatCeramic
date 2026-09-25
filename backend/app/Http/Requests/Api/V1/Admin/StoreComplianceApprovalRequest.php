<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplianceApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['business_owner', 'data_protection_officer', 'legal_reviewer'])],
            'reviewer_name' => ['required', 'string', 'max:255'],
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decided_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'document_version_id' => ['required', 'integer', Rule::exists('legal_document_versions', 'id')],
        ];
    }
}
