<?php

namespace Tests\Unit;

use App\Support\RussianPhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RussianPhoneNumberTest extends TestCase
{
    /** @return iterable<string, array{mixed, ?string}> */
    public static function numbers(): iterable
    {
        yield 'canonical' => ['+79991234567', '+79991234567'];
        yield 'formatted' => [' +7 (999) 123-45-67 ', '+79991234567'];
        yield 'national' => ['9991234567', '+79991234567'];
        yield 'legacy' => ['8 (999) 123-45-67', '+79991234567'];
        yield 'foreign' => ['+19991234567', null];
        yield 'too short' => ['+7999123456', null];
        yield 'too long' => ['+799912345678', null];
        yield 'extension' => ['+79991234567 доб. 5', null];
        yield 'letters' => ['a9991234567', null];
        yield 'extra plus' => ['++79991234567', null];
        yield 'array' => [['9991234567'], null];
        yield 'integer' => [9991234567, null];
        yield 'empty' => ['', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('numbers')]
    public function test_normalizes_only_complete_supported_russian_numbers(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, RussianPhoneNumber::normalize($input));
    }
}
