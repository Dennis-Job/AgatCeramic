<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ContactStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'month' => ['required', 'string', 'regex:/^[0-9]{4}-(0[1-9]|1[0-2])$/D', 'date_format:Y-m', 'after_or_equal:1900-01', 'before_or_equal:9998-12'],
        ];
    }
}
