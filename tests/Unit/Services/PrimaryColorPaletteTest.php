<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Services\PrimaryColorPalette;
use PHPUnit\Framework\TestCase;

final class PrimaryColorPaletteTest extends TestCase
{
    public function testIsValidHexAcceptsSixDigitHexOnly(): void
    {
        $this->assertTrue(PrimaryColorPalette::isValidHex('#ff9f4a'));
        $this->assertTrue(PrimaryColorPalette::isValidHex('#FF9F4A'));
        $this->assertFalse(PrimaryColorPalette::isValidHex('#fff'));
        $this->assertFalse(PrimaryColorPalette::isValidHex('ff9f4a'));
        $this->assertFalse(PrimaryColorPalette::isValidHex('red'));
        $this->assertFalse(PrimaryColorPalette::isValidHex('#gggggg'));
    }

    public function testGenerateFallsBackToDefaultOnInvalidInput(): void
    {
        $palette = PrimaryColorPalette::generate('not-a-color');
        $this->assertSame(PrimaryColorPalette::DEFAULT_COLOR, $palette['--light-primary']);
    }

    public function testGenerateReturnsAllExpectedTokens(): void
    {
        $palette = PrimaryColorPalette::generate('#6c5ce7');

        $this->assertSame([
            '--light-primary',
            '--light-primary-rgb',
            '--light-primary-hover',
            '--light-primary-light',
            '--light-primary-lighter',
            '--light-primary-medium',
            '--dark-primary',
            '--dark-primary-hover',
            '--dark-primary-light',
            '--dark-primary-lighter',
            '--dark-primary-medium',
        ], array_keys($palette));

        $this->assertSame('#6c5ce7', $palette['--light-primary']);
        $this->assertSame('108, 92, 231', $palette['--light-primary-rgb']);
    }

    public function testGenerateUsesRealMascotColorsForTheDefault(): void
    {
        $palette = PrimaryColorPalette::generate(PrimaryColorPalette::DEFAULT_COLOR);

        // Couleurs officielles du style guide (docs/brand/foxy-style-guide.png),
        // pas des teintes calculées : pelage, nez/pattes, ventre.
        $this->assertSame('#ff9f4a', $palette['--light-primary']);
        $this->assertSame('#5b4a3a', $palette['--light-primary-hover']);
        $this->assertSame('#fff5e6', $palette['--light-primary-light']);
    }

    public function testGenerateComputesHoverForCustomColors(): void
    {
        $palette = PrimaryColorPalette::generate('#2f86d6');

        // Une couleur personnalisée n'a pas de teintes "toutes faites" dans la
        // palette de la mascotte : elle reste dérivée par calcul HSL.
        $this->assertNotSame('#5b4a3a', $palette['--light-primary-hover']);
        $this->assertNotSame('#fff5e6', $palette['--light-primary-light']);
    }

    public function testHoverIsDarkerThanBaseInLightTheme(): void
    {
        $palette = PrimaryColorPalette::generate('#ff9f4a');

        $baseLuminance  = self::relativeLuminance($palette['--light-primary']);
        $hoverLuminance = self::relativeLuminance($palette['--light-primary-hover']);

        $this->assertLessThan($baseLuminance, $hoverLuminance);
    }

    public function testDarkVariantIsLighterThanLightBaseForContrastOnDarkBackground(): void
    {
        $palette = PrimaryColorPalette::generate('#ff9f4a');

        $lightLuminance = self::relativeLuminance($palette['--light-primary']);
        $darkLuminance  = self::relativeLuminance($palette['--dark-primary']);

        $this->assertGreaterThan($lightLuminance, $darkLuminance);
    }

    public function testDarkTintsAreRgbaOfDarkBase(): void
    {
        $palette = PrimaryColorPalette::generate('#ff9f4a');

        $this->assertMatchesRegularExpression('/^rgba\(\d+, \d+, \d+, 0\.16\)$/', $palette['--dark-primary-light']);
        $this->assertMatchesRegularExpression('/^rgba\(\d+, \d+, \d+, 0\.08\)$/', $palette['--dark-primary-lighter']);
        $this->assertMatchesRegularExpression('/^rgba\(\d+, \d+, \d+, 0\.30\)$/', $palette['--dark-primary-medium']);
    }

    public function testToInlineStyleConcatenatesAllTokens(): void
    {
        $style = PrimaryColorPalette::toInlineStyle('#ff9f4a');

        $this->assertStringContainsString('--light-primary:#ff9f4a;', $style);
        $this->assertStringContainsString('--dark-primary:', $style);
    }

    private static function relativeLuminance(string $hex): float
    {
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
