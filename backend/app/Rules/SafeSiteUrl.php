<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeSiteUrl implements ValidationRule
{
    public function __construct(private readonly bool $allowLegacyHttp = false) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) {
            $fail('Ссылка должна быть внутренним путём или безопасным HTTPS URL.');

            return;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            $parts = parse_url($value);
            $allowedSchemes = $this->allowLegacyHttp ? ['https', 'http'] : ['https'];
            if (is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), $allowedSchemes, true)
                && ! array_key_exists('user', $parts) && ! array_key_exists('pass', $parts)) {
                return;
            }
        }

        $fail('Ссылка должна быть внутренним путём или безопасным HTTPS URL.');
    }
}
