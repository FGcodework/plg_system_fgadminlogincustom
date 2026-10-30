<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @license     GNU General Public License version 2 or later
 *
 * Validates settings values coming from an imported JSON, key by key.
 * Executable code (custom_js) is never importable; see the plugin class.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Helper;

\defined('_JEXEC') or die;

final class SettingsSanitizer
{
    private const COLORS = [
        'bg_color', 'grad_start', 'grad_end', 'bg_overlay', 'brand_color', 'card_bg', 'card_text',
        'btn_bg', 'btn_hover', 'btn_text', 'link_color', 'header_bg', 'header_text',
    ];

    private const MEDIA = ['logo', 'logo_dark', 'bg_image'];

    private const FLAGS = [
        'hide_template_logo', 'hide_sidebar', 'hide_generator', 'hide_forgot', 'hide_chrome',
        'hide_lang_switcher', 'header_match_btn',
    ];

    private const SHADOWS = ['none', 'subtle', 'medium', 'strong'];

    /** @var array<string, array{0: int, 1: int}> */
    private const RANGES = [
        'logo_width'         => [40, 800],
        'logo_height'        => [20, 600],
        'grad_angle'         => [0, 360],
        'bg_overlay_opacity' => [0, 100],
        'card_radius'        => [0, 60],
        'ctrl_radius'        => [0, 40],
    ];

    /** @var array<string, string[]> */
    private const LISTS = [
        'logo_fit'    => ['contain', 'cover'],
        'bg_type'     => ['none', 'color', 'gradient', 'image'],
        'card_shadow' => self::SHADOWS,
        'btn_shadow'  => self::SHADOWS,
    ];

    /**
     * Names of all settings an import may set.
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_merge(
            self::COLORS,
            self::MEDIA,
            self::FLAGS,
            array_keys(self::RANGES),
            array_keys(self::LISTS),
            ['footer_text', 'custom_css']
        );
    }

    /**
     * Returns the valid values (normalised) and the names of the rejected keys.
     * Keys that are not importable (unknown, custom_js, the preset trigger, the
     * export/import fields) are ignored silently.
     *
     * @param array<string, mixed> $input
     * @return array{values: array<string, string|int>, rejected: string[]}
     */
    public static function sanitize(array $input): array
    {
        $values   = [];
        $rejected = [];

        foreach ($input as $key => $value) {
            $key = (string) $key;

            if (!\in_array($key, self::keys(), true)) {
                continue;
            }

            $clean = self::cleanValue($key, $value);

            if ($clean === null) {
                $rejected[] = $key;
                continue;
            }

            $values[$key] = $clean;
        }

        return ['values' => $values, 'rejected' => $rejected];
    }

    /**
     * @param mixed $value
     * @return string|int|null null = invalid
     */
    private static function cleanValue(string $key, $value)
    {
        if (!\is_scalar($value) && $value !== null) {
            return null;
        }

        $text = trim((string) $value);

        if (\in_array($key, self::COLORS, true)) {
            return $text === '' ? '' : (ColorHelper::clean($text) ?: null);
        }

        if (\in_array($key, self::MEDIA, true)) {
            return self::cleanPath($text);
        }

        if (isset(self::RANGES[$key])) {
            if ($text === '') {
                return '';
            }

            if (preg_match('/^\d{1,3}$/', $text) !== 1) {
                return null;
            }

            [$min, $max] = self::RANGES[$key];
            $number      = (int) $text;

            return $number >= $min && $number <= $max ? $number : null;
        }

        if (\in_array($key, self::FLAGS, true)) {
            return $text === '0' || $text === '1' ? (int) $text : null;
        }

        if (isset(self::LISTS[$key])) {
            return \in_array($text, self::LISTS[$key], true) ? $text : null;
        }

        if ($key === 'footer_text') {
            return self::cleanFooter($text);
        }

        // custom_css
        return self::cleanCss($text);
    }

    /**
     * A path or URL: no markup, quotes, backslashes, control characters or script schemes.
     */
    private static function cleanPath(string $text): ?string
    {
        if ($text === '') {
            return '';
        }

        if (\strlen($text) > 500 || preg_match('/[<>"\'\\\\\x00-\x1f]/', $text) === 1
            || preg_match('#^\s*(javascript|data|vbscript):#i', $text) === 1) {
            return null;
        }

        return $text;
    }

    /**
     * Footer HTML is printed raw on the login page, so an import must not
     * carry scripts, frames, inline event handlers or javascript: links.
     */
    private static function cleanFooter(string $text): ?string
    {
        if (\strlen($text) > 5000) {
            return null;
        }

        if (preg_match('#<\s*/?\s*(script|iframe|object|embed|style|link|meta|base|form)\b#i', $text) === 1
            || preg_match('#\bon[a-z]+\s*=#i', $text) === 1
            || preg_match('#(javascript|vbscript)\s*:#i', $text) === 1) {
            return null;
        }

        return $text;
    }

    /**
     * Custom CSS must not be able to leave its <style> element.
     */
    private static function cleanCss(string $text): ?string
    {
        if (\strlen($text) > 50000 || preg_match('#<\s*/?\s*(style|script)\b#i', $text) === 1) {
            return null;
        }

        return $text;
    }
}
