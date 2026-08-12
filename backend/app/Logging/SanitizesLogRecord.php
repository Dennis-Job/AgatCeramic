<?php

namespace App\Logging;

use DateTimeInterface;
use Monolog\LogRecord;
use Throwable;

final class SanitizesLogRecord
{
    private const REDACTED = '[redacted]';

    private const SENSITIVE_KEY_PATTERN = '/(?:^|[_\\-.])(?:address|api[_-]?key|authorization|card|comment|cookie|cvv|delivery[_-]?address|email|e-mail|first[_-]?name|full[_-]?name|last[_-]?name|message|middle[_-]?name|name|pan|passphrase|password|phone|private[_-]?key|refresh[_-]?token|secret|token)(?:$|[_\\-.])/i';

    private const EMAIL_PATTERN = '/[A-Z0-9._%+\\-]+@[A-Z0-9.\\-]+\\.[A-Z]{2,}/i';

    private const PHONE_PATTERN = '/(?<!\\d)\\+?\\d(?:[\\s().-]*\\d){9,14}(?!\\d)/';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->sanitizeString($record->message),
            context: $this->sanitizeArray($record->context),
            extra: $this->sanitizeArray($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $sanitized[$key] = $this->isSensitiveKey((string) $key)
                ? self::REDACTED
                : $this->sanitizeValue($value);
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if ($value instanceof DateTimeInterface || is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof Throwable) {
            return [
                'exception' => $value::class,
            ];
        }

        return [
            'object' => $value::class,
        ];
    }

    private function sanitizeString(string $value): string
    {
        return preg_replace(
            [self::EMAIL_PATTERN, self::PHONE_PATTERN],
            self::REDACTED,
            $value,
        ) ?? self::REDACTED;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1;
    }
}
