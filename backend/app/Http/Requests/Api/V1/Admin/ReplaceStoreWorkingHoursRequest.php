<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use LogicException;

class ReplaceStoreWorkingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('store'));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'working_hours' => ['required', 'array', 'size:7'],
            'working_hours.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'working_hours.*.is_closed' => ['required', 'boolean'],
            'working_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'working_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /** @return list<array{weekday:int,is_closed:bool,opens_at:?string,closes_at:?string}> */
    public function workingHours(): array
    {
        $validated = $this->validated('working_hours');
        if (! is_array($validated)) {
            throw new LogicException('Validated working_hours must be an array.');
        }
        $hours = [];
        foreach ($validated as $day) {
            if (! is_array($day)) {
                throw new LogicException('Validated working_hours must contain objects.');
            }
            $weekday = $day['weekday'] ?? null;
            $closed = $day['is_closed'] ?? null;
            $open = $day['opens_at'] ?? null;
            $close = $day['closes_at'] ?? null;
            if ((! is_int($weekday) && (! is_string($weekday) || ! ctype_digit($weekday)))
                || (! is_bool($closed) && ! in_array($closed, [0, 1, '0', '1'], true))
                || ($open !== null && ! is_string($open))
                || ($close !== null && ! is_string($close))) {
                throw new LogicException('Validated working_hours has unexpected types.');
            }
            $hours[] = [
                'weekday' => (int) $weekday,
                'is_closed' => (bool) $closed,
                'opens_at' => $open,
                'closes_at' => $close,
            ];
        }

        return $hours;
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $hours = $this->workingHours();
            foreach ($hours as $index => $day) {
                $closed = (bool) $day['is_closed'];
                $open = $day['opens_at'] ?? null;
                $close = $day['closes_at'] ?? null;
                if ($closed && ($open !== null || $close !== null)) {
                    $validator->errors()->add("working_hours.$index", 'Closed days must not have opening times.');
                } elseif (! $closed && ($open === null || $close === null || $open >= $close)) {
                    $validator->errors()->add("working_hours.$index", 'Opening time must be before closing time.');
                }
            }
        }];
    }
}
