<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:zip', 'max:512000']];
    }

    #[\Override]
    public function messages(): array
    {
        return ['file.required' => 'Выберите ZIP-архив с изображениями.', 'file.mimes' => 'Поддерживается только ZIP-архив.', 'file.max' => 'Размер ZIP-архива не должен превышать 500 МБ.'];
    }
}
