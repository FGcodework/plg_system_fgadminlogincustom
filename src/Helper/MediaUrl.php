<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @license     GNU General Public License version 2 or later
 *
 * Normalises a media-field value to a usable URL.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Helper;

use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

final class MediaUrl
{
    /**
     * Strips the #joomlaImage adapter fragment from a media field value
     * and returns an absolute URL, or an empty string.
     */
    public static function clean(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Remove the "#joomlaImage://..." fragment appended by the media field.
        $hashPos = strpos($value, '#');

        if ($hashPos !== false) {
            $value = substr($value, 0, $hashPos);
        }

        if ($value === '') {
            return '';
        }

        // Already absolute?
        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return $value;
        }

        return Uri::root(true) . '/' . ltrim($value, '/');
    }
}
