<?php

namespace Tests\Unit;

use App\Support\GovernmentIdNumbers;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GovernmentIdNumbersTest extends TestCase
{
    #[DataProvider('validSamples')]
    public function test_accepts_valid_government_numbers(string $type, string $value): void
    {
        $this->assertTrue(GovernmentIdNumbers::isValid($value, $type));
    }

    #[DataProvider('invalidSamples')]
    public function test_rejects_invalid_government_numbers(string $type, string $value): void
    {
        $this->assertFalse(GovernmentIdNumbers::isValid($value, $type));
    }

    public function test_blank_values_are_allowed(): void
    {
        $this->assertTrue(GovernmentIdNumbers::isValid(null, GovernmentIdNumbers::TYPE_SSS));
        $this->assertTrue(GovernmentIdNumbers::isValid('', GovernmentIdNumbers::TYPE_TIN));
        $this->assertTrue(GovernmentIdNumbers::isValid('   ', GovernmentIdNumbers::TYPE_PAGIBIG));
    }

    public function test_normalize_strips_non_digits(): void
    {
        $this->assertSame('3412345678', GovernmentIdNumbers::normalize('34-1234567-8'));
        $this->assertNull(GovernmentIdNumbers::normalize(''));
    }

    public function test_format_adds_display_dashes(): void
    {
        $this->assertSame('34-1234567-8', GovernmentIdNumbers::format('3412345678', GovernmentIdNumbers::TYPE_SSS));
        $this->assertSame('123-456-789', GovernmentIdNumbers::format('123456789', GovernmentIdNumbers::TYPE_TIN));
        $this->assertSame('123-456-789-000', GovernmentIdNumbers::format('123456789000', GovernmentIdNumbers::TYPE_TIN));
    }

    public function test_is_valid_accepts_dashed_input_before_normalization(): void
    {
        $this->assertTrue(GovernmentIdNumbers::isValid('34-1234567-8', GovernmentIdNumbers::TYPE_SSS));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validSamples(): array
    {
        return [
            'sss digits only' => [GovernmentIdNumbers::TYPE_SSS, '3412345678'],
            'philhealth digits only' => [GovernmentIdNumbers::TYPE_PHILHEALTH, '123456789012'],
            'pagibig digits only' => [GovernmentIdNumbers::TYPE_PAGIBIG, '123456789012'],
            'tin 9 digits' => [GovernmentIdNumbers::TYPE_TIN, '123456789'],
            'tin 12 digits' => [GovernmentIdNumbers::TYPE_TIN, '123456789000'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidSamples(): array
    {
        return [
            'sss too short' => [GovernmentIdNumbers::TYPE_SSS, '341234567'],
            'sss letters' => [GovernmentIdNumbers::TYPE_SSS, '34ABCDEFG8'],
            'philhealth too short' => [GovernmentIdNumbers::TYPE_PHILHEALTH, '12345678901'],
            'pagibig too long' => [GovernmentIdNumbers::TYPE_PAGIBIG, '1234567890123'],
            'tin wrong length' => [GovernmentIdNumbers::TYPE_TIN, '12345678'],
        ];
    }
}
