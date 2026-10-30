<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @license     GNU General Public License version 2 or later
 *
 * Pure color maths (hex only), free of any Joomla dependency so it can be unit tested.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Helper;

\defined('_JEXEC') or die;

final class ColorHelper
{
    /**
     * Validates a hex color (#rgb, #rrggbb, #rrggbbaa); returns it lower-cased or ''.
     */
    public static function clean(string $value): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) !== 1) {
            return '';
        }

        return strtolower($value);
    }

    /**
     * More readable of black/white on a hex background (WCAG contrast ratio).
     */
    public static function contrastText(string $hexBg): string
    {
        $luminance = self::luminance($hexBg);

        return 1.05 / ($luminance + 0.05) >= ($luminance + 0.05) / 0.05 ? '#ffffff' : '#000000';
    }

    /**
     * WCAG relative luminance (0..1).
     */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::toRgb($hex);

        $linearize = static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $linearize($r) + 0.7152 * $linearize($g) + 0.0722 * $linearize($b);
    }

    /**
     * WCAG contrast ratio (1..21) between two hex colors.
     */
    public static function contrast(string $hexA, string $hexB): float
    {
        $a = self::luminance($hexA);
        $b = self::luminance($hexB);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Darkens (negative $amount) or lightens (positive) a color by mixing it
     * with black or white; any alpha channel is dropped.
     */
    public static function shade(string $hex, float $amount): string
    {
        $target = $amount < 0 ? 0 : 255;
        $weight = min(1.0, abs($amount));
        $out    = '#';

        foreach (self::toRgb($hex) as $channel) {
            $out .= sprintf('%02x', (int) round($channel + ($target - $channel) * $weight));
        }

        return $out;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (\strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Hex color + opacity percentage as an rgba() string.
     */
    public static function toRgba(string $hex, int $opacityPercent): string
    {
        [$r, $g, $b] = self::toRgb($hex);

        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, max(0, min(100, $opacityPercent)) / 100);
    }
}
