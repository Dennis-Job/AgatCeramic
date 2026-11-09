<?php

namespace App\Rules;

use App\Support\LegalDocumentContent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class LegalDocumentContentRule implements ValidationRule
{
    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            (new LegalDocumentContent)->toPlainText($value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
