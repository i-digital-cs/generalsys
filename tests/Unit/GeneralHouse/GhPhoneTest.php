<?php

namespace Tests\Unit\GeneralHouse;

use App\Support\GeneralHouse\GhPhone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GhPhoneTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, GhPhone::normalize($input));
    }

    public function test_normalize_empty(): void
    {
        $this->assertNull(GhPhone::normalize(null));
        $this->assertNull(GhPhone::normalize(''));
        $this->assertNull(GhPhone::normalize('   '));
        $this->assertNull(GhPhone::normalize('---'));
    }

    /** @return list<array{0: string, 1: string}> */
    public static function normalizeProvider(): array
    {
        return [
            ['0501234567', '971501234567'],
            ['+971 50 123 4567', '971501234567'],
            ['00971501234567', '971501234567'],
            ['971501234567', '971501234567'],
            ['501234567', '971501234567'],
            ['(050) 123-4567', '971501234567'],
        ];
    }
}
