<?php

namespace Tests\Unit;

use App\Support\BangladeshPhone;
use App\Support\Taka;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupportFormattingTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function phoneCases(): array
    {
        return [
            'local' => ['01712345678', '01712345678'],
            'with +88' => ['+8801712345678', '01712345678'],
            'with 88' => ['8801712345678', '01712345678'],
            'spaces and dashes' => ['017-1234 5678', '01712345678'],
            'invalid operator prefix' => ['01212345678', null],
            'too short' => ['0171234567', null],
            'letters' => ['01712abc678', null],
        ];
    }

    #[DataProvider('phoneCases')]
    public function test_bangladesh_phone_normalisation(string $input, ?string $expected): void
    {
        $this->assertSame($expected, BangladeshPhone::normalize($input));
    }

    public function test_taka_uses_south_asian_grouping(): void
    {
        $this->assertSame('৳115', Taka::format(11500));
        $this->assertSame('৳62,900', Taka::format(6290000));
        $this->assertSame('৳1,28,90,000', Taka::format(1289000000));
        $this->assertSame('৳812.50', Taka::format(81250));
        $this->assertSame('৳0.05', Taka::format(5));
    }

    public function test_round_half_up(): void
    {
        $this->assertSame(1, Taka::divideRoundHalfUp(500, 1000));
        $this->assertSame(0, Taka::divideRoundHalfUp(499, 1000));
    }
}
