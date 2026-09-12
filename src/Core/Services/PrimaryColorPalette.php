<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * Dérive, à partir d'une seule couleur de marque choisie par l'Owner, l'ensemble
 * des variantes (hover, teintes claires, version adaptée au mode sombre) que
 * `variables.css` attend pour la famille de tokens --light-primary-* et --dark-primary-*.
 * Évite de devoir stocker/maintenir 10 couleurs par instance pour un seul réglage.
 */
final class PrimaryColorPalette
{
    /** Orange du pelage de Foxy, la mascotte de l'app (docs/brand/foxy-style-guide.png). */
    public const DEFAULT_COLOR = '#ff9f4a';

    public static function isValidHex(string $hex): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $hex);
    }

    /** @return array<string, string> variables CSS prêtes à injecter en style inline sur <html> */
    public static function generate(string $hex): array
    {
        if (!self::isValidHex($hex)) {
            $hex = self::DEFAULT_COLOR;
        }

        [$h, $s, $l] = self::hexToHsl($hex);

        $darkS = max(0.0, $s - 0.05);
        $darkL = min(0.92, $l + 0.18);
        $darkBase = self::hslToHex($h, $darkS, $darkL);
        $darkHover = self::hslToHex($h, $darkS, min(0.96, $darkL + 0.08));
        [$dr, $dg, $db] = self::hexToRgb($darkBase);

        return [
            '--light-primary'         => $hex,
            '--light-primary-hover'   => self::hslToHex($h, $s, max(0.0, $l - 0.12)),
            '--light-primary-light'   => self::tint($hex, 0.88),
            '--light-primary-lighter' => self::tint($hex, 0.94),
            '--light-primary-medium'  => self::tint($hex, 0.68),
            '--dark-primary'          => $darkBase,
            '--dark-primary-hover'    => $darkHover,
            '--dark-primary-light'    => sprintf('rgba(%d, %d, %d, 0.16)', $dr, $dg, $db),
            '--dark-primary-lighter'  => sprintf('rgba(%d, %d, %d, 0.08)', $dr, $dg, $db),
            '--dark-primary-medium'   => sprintf('rgba(%d, %d, %d, 0.30)', $dr, $dg, $db),
        ];
    }

    public static function toInlineStyle(string $hex): string
    {
        $css = '';
        foreach (self::generate($hex) as $var => $value) {
            $css .= $var . ':' . $value . ';';
        }
        return $css;
    }

    private static function tint(string $hex, float $whiteRatio): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $mix = static fn (int $c): int => (int) round($c * (1 - $whiteRatio) + 255 * $whiteRatio);
        return self::rgbToHex($mix($r), $mix($g), $mix($b));
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexToRgb(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    private static function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    /** @return array{0:float,1:float,2:float} teinte (0-360), saturation et luminosité (0-1) */
    private static function hexToHsl(string $hex): array
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $delta = $max - $min;
        $s = $l > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);

        $h = match ($max) {
            $r => fmod(($g - $b) / $delta, 6),
            $g => ($b - $r) / $delta + 2,
            default => ($r - $g) / $delta + 4,
        };
        $h *= 60;
        if ($h < 0) {
            $h += 360;
        }

        return [$h, $s, $l];
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        $s = max(0.0, min(1.0, $s));
        $l = max(0.0, min(1.0, $l));

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0.0],
            $h < 120 => [$x, $c, 0.0],
            $h < 180 => [0.0, $c, $x],
            $h < 240 => [0.0, $x, $c],
            $h < 300 => [$x, 0.0, $c],
            default => [$c, 0.0, $x],
        };

        return self::rgbToHex(
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        );
    }
}
