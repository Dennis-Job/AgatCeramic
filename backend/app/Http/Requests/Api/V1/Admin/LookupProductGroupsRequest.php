<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class LookupProductGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', ProductGroup::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255']];
    }
}
