<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @version     1.19.4
 * @license     GNU General Public License version 2 or later
 *
 * Optional shared look with plg_system_fgadminpagecustom (FG Admin Page
 * Customizer): when switched on, brand color, corner radii and shadow presets
 * are taken from that plugin's settings, so they only have to be entered once.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Helper;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

final class SharedStyle
{
    private const SHADOWS = ['none', 'subtle', 'medium', 'strong'];

    /** @var array<string, array{0: int, 1: int}> */
    private const RADII = [
        'ctrl_radius' => [0, 40],
        'card_radius' => [0, 60],
    ];

    /**
     * Returns the effective settings: a copy of $own with the shared values
     * of $page laid over it. $own itself is never modified. Without $page
     * (plugin missing or disabled) or with the switch off, $own is returned
     * unchanged; invalid or empty shared values are ignored.
     */
    public static function apply(Registry $own, ?Registry $page): Registry
    {
        if ($page === null || (int) $own->get('use_page_style', 0) !== 1) {
            return $own;
        }

        $result = new Registry($own->toArray());

        $brand = ColorHelper::clean(trim((string) $page->get('brand_color', '')));

        if ($brand !== '') {
            $result->set('brand_color', $brand);
        }

        foreach (self::RADII as $key => [$min, $max]) {
            $value = trim((string) $page->get($key, ''));

            if (preg_match('/^\d{1,3}$/', $value) === 1 && (int) $value >= $min && (int) $value <= $max) {
                $result->set($key, (string) (int) $value);
            }
        }

        foreach (['btn_shadow', 'card_shadow'] as $key) {
            $value = (string) $page->get($key, '');

            if (\in_array($value, self::SHADOWS, true)) {
                $result->set($key, $value);
            }
        }

        return $result;
    }
}
