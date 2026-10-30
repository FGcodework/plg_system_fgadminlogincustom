<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @license     GNU General Public License version 2 or later
 *
 * Curated color combinations applied in one go.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Helper;

\defined('_JEXEC') or die;

final class ThemePresets
{
    /**
     * Built-in color presets. Deliberately touch only color/background
     * fields, never layout/privacy toggles (hide_sidebar, hide_chrome, ...)
     * so applying a preset never silently changes structural behaviour the
     * admin configured separately. "reset" clears overrides back to the
     * Atum template's own colors.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $empty = [
            'bg_type' => 'none', 'bg_color' => '', 'grad_start' => '', 'grad_end' => '',
            'card_bg' => '', 'card_text' => '', 'card_radius' => '',
            'btn_bg' => '', 'btn_hover' => '', 'btn_text' => '', 'link_color' => '',
            'header_bg' => '', 'header_text' => '', 'brand_color' => '',
        ];

        return [
            'reset' => $empty,
            'dark' => array_merge($empty, [
                'bg_type' => 'gradient', 'grad_start' => '#1a1a2e', 'grad_end' => '#0f0f1a', 'grad_angle' => 135,
                'card_bg' => '#22223b', 'card_text' => '#e0e0e0', 'card_radius' => 12,
                'btn_bg' => '#4a4e69', 'btn_hover' => '#383a5c', 'btn_text' => '#ffffff',
                'link_color' => '#9a8c98', 'header_bg' => '#0f0f1a', 'header_text' => '#e0e0e0',
            ]),
            'corporate_blue' => array_merge($empty, [
                'bg_type' => 'gradient', 'grad_start' => '#1c3d5c', 'grad_end' => '#0e1e2e', 'grad_angle' => 135,
                'card_bg' => '#ffffff', 'card_text' => '#1c2733', 'card_radius' => 8,
                'btn_bg' => '#1c3d5c', 'btn_hover' => '#14293f', 'btn_text' => '#ffffff',
                'link_color' => '#2a69b8', 'header_bg' => '#1c3d5c', 'header_text' => '#ffffff',
            ]),
            'green' => array_merge($empty, [
                'bg_type' => 'gradient', 'grad_start' => '#1b4332', 'grad_end' => '#081c15', 'grad_angle' => 135,
                'card_bg' => '#ffffff', 'card_text' => '#1b4332', 'card_radius' => 10,
                'btn_bg' => '#2d6a4f', 'btn_hover' => '#1b4332', 'btn_text' => '#ffffff',
                'link_color' => '#40916c', 'header_bg' => '#1b4332', 'header_text' => '#ffffff',
            ]),
            'orange' => array_merge($empty, [
                'bg_type' => 'gradient', 'grad_start' => '#7c2d12', 'grad_end' => '#431407', 'grad_angle' => 135,
                'card_bg' => '#ffffff', 'card_text' => '#431407', 'card_radius' => 10,
                'btn_bg' => '#c2410c', 'btn_hover' => '#9a3412', 'btn_text' => '#ffffff',
                'link_color' => '#ea580c', 'header_bg' => '#7c2d12', 'header_text' => '#ffffff',
            ]),
            'minimal' => array_merge($empty, [
                'bg_type' => 'color', 'bg_color' => '#f5f5f5',
                'card_bg' => '#ffffff', 'card_text' => '#333333', 'card_radius' => 4,
                'btn_bg' => '#333333', 'btn_hover' => '#000000', 'btn_text' => '#ffffff',
                'link_color' => '#555555', 'header_bg' => '#ffffff', 'header_text' => '#333333',
            ]),
        ];
    }
}
