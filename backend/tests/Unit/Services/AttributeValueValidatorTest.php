<?php

namespace Tests\Unit\Services;

use App\Services\AttributeValueValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttributeValueValidatorTest extends TestCase
{
    #[Test]
    #[DataProvider('validValues')]
    public function it_accepts_values_matching_every_catalog_attribute_type(string $type, mixed $value, array $options = []): void
    {
        self::assertTrue(app(AttributeValueValidator::class)->isValid($type, $value, $options));
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function it_rejects_values_that_do_not_match_the_catalog_attribute_type(string $type, mixed $value, array $options = []): void
    {
        self::assertFalse(app(AttributeValueValidator::class)->isValid($type, $value, $options));
    }

    /** @return array<string, array{string, mixed, 2?: array<int, string>}> */
    public static function validValues(): array
    {
        return [
            'string' => ['string', 'Глянцевая'],
            'text' => ['text', "Первая строка\nВторая строка"],
            'integer' => ['integer', 12],
            'decimal' => ['decimal', 12.5],
            'boolean' => ['boolean', true],
            'select' => ['select', 'matte', ['matte']],
            'multiselect' => ['multiselect', ['matte', 'glossy'], ['matte', 'glossy']],
            'date' => ['date', '2024-02-29'],
        ];
    }

    /** @return array<string, array{string, mixed, 2?: array<int, string>}> */
    public static function invalidValues(): array
    {
        return [
            'empty string' => ['string', ''],
            'empty text' => ['text', ''],
            'oversized string' => ['string', str_repeat('a', 256)],
            'oversized text' => ['text', str_repeat('a', 10001)],
            'integer string' => ['integer', '12'],
            'decimal string' => ['decimal', '12.5'],
            'boolean string' => ['boolean', 'true'],
            'unknown select option' => ['select', 'glossy', ['matte']],
            'duplicate multiselect options' => ['multiselect', ['matte', 'matte'], ['matte']],
            'impossible date' => ['date', '2023-02-29'],
        ];
    }
}
