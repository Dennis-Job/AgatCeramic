<?php

namespace Tests\Unit\Logging;

use App\Logging\SanitizesLogRecord;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Tests\TestCase;

class SanitizesLogRecordTest extends TestCase
{
    public function test_it_masks_sensitive_context_values_and_pii_in_free_text(): void
    {
        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Info,
            'Новый заказ: ivan@example.test, телефон +7 (999) 123-45-67.',
            [
                'email' => 'ivan@example.test',
                'password' => 'not-a-password',
                'order' => [
                    'delivery_address' => 'Москва, улица Пример, 1',
                    'quantity' => 2,
                ],
                'note' => 'Перезвонить по номеру +7 999 123 45 67.',
                'exception' => new RuntimeException('ivan@example.test'),
            ],
            [
                'phone' => '+79991234567',
            ],
        );

        $sanitized = (new SanitizesLogRecord)($record);

        $this->assertSame('Новый заказ: [redacted], телефон [redacted].', $sanitized->message);
        $this->assertSame('[redacted]', $sanitized->context['email']);
        $this->assertSame('[redacted]', $sanitized->context['password']);
        $this->assertSame('[redacted]', $sanitized->context['order']['delivery_address']);
        $this->assertSame(2, $sanitized->context['order']['quantity']);
        $this->assertSame('Перезвонить по номеру [redacted].', $sanitized->context['note']);
        $this->assertSame(['exception' => RuntimeException::class], $sanitized->context['exception']);
        $this->assertSame('[redacted]', $sanitized->extra['phone']);
    }

    public function test_it_preserves_non_sensitive_operational_context(): void
    {
        $record = new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Info,
            'Импорт товаров завершён.',
            [
                'import_id' => 31,
                'processed_rows' => 128,
                'status' => 'completed',
            ],
        );

        $sanitized = (new SanitizesLogRecord)($record);

        $this->assertSame('Импорт товаров завершён.', $sanitized->message);
        $this->assertSame([
            'import_id' => 31,
            'processed_rows' => 128,
            'status' => 'completed',
        ], $sanitized->context);
    }
}
