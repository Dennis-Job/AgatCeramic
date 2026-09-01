<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip',
                'max:10240',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите XLSX-файл с товарами.',
            'file.file' => 'Не удалось прочитать загруженный файл.',
            'file.mimes' => 'Для импорта поддерживаются только XLSX-файлы.',
            'file.mimetypes' => 'Содержимое файла не соответствует формату XLSX.',
            'file.max' => 'Размер XLSX-файла не должен превышать 10 МБ.',
        ];
    }
}
