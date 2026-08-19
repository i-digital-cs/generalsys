<?php

namespace Tests\Unit\Support;

use App\Support\DompdfArabic;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DompdfArabicTest extends TestCase
{
    #[Test]
    public function it_shapes_arabic_for_dompdf(): void
    {
        $source = 'كشف الراتب';
        $shaped = DompdfArabic::glyphs($source);

        $this->assertNotSame($source, $shaped);
        $this->assertNotSame(strrev($source), $shaped);
        // Presentation forms (FE70–FEFF) after shaping — Dompdf can paint these.
        $this->assertMatchesRegularExpression('/[\x{FE70}-\x{FEFF}]/u', $shaped);
    }

    #[Test]
    public function it_leaves_latin_unchanged(): void
    {
        $this->assertSame('Payroll slip', DompdfArabic::glyphs('Payroll slip'));
        $this->assertSame('', DompdfArabic::glyphs(''));
        $this->assertSame('', DompdfArabic::glyphs(null));
    }

    #[Test]
    public function it_shapes_mixed_arabic_and_latin(): void
    {
        $shaped = DompdfArabic::glyphs('Team Leader · مسودة');

        $this->assertStringContainsString('Team Leader', $shaped);
        $this->assertMatchesRegularExpression('/[\x{FE70}-\x{FEFF}]/u', $shaped);
    }

    #[Test]
    public function it_keeps_latin_digits_and_brackets_in_mixed_arabic(): void
    {
        $shaped = DompdfArabic::glyphs('النسبة % (0–100)');

        $this->assertStringContainsString('(0–100)', $shaped);
        $this->assertStringNotContainsString(')0)', $shaped);
        $this->assertStringNotContainsString('٠', $shaped);
        $this->assertMatchesRegularExpression('/[\x{FE70}-\x{FEFF}]/u', $shaped);

        $king = DompdfArabic::glyphs('تشغيل المغسلة — King');
        $this->assertStringContainsString('King', $king);
        $this->assertStringContainsString('—', $king);

        $b2b = DompdfArabic::glyphs('تارجت B2B');
        $this->assertStringContainsString('B2B', $b2b);
        $this->assertStringNotContainsString('B٢B', $b2b);
    }
}
