<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @license     GNU General Public License version 2 or later
 *
 * Shape-preserving read/write of plugin params inside the form data of the
 * plugin edit form (settings export / import).
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Helper;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

final class ImportExport
{
    /**
     * Plugin params in any of the shapes Joomla hands over, as a plain array.
     *
     * @param mixed $paramsData
     * @return array<string, mixed>
     */
    public static function paramsToArray($paramsData): array
    {
        if ($paramsData instanceof Registry) {
            return $paramsData->toArray();
        }

        if (\is_string($paramsData)) {
            $decoded = $paramsData === '' ? [] : json_decode($paramsData, true);

            return \is_array($decoded) ? $decoded : [];
        }

        if (\is_object($paramsData)) {
            return (array) $paramsData;
        }

        return \is_array($paramsData) ? $paramsData : [];
    }

    /**
     * Writes each key/value from $values onto $data->params (mutating the
     * pre-fill data object in place, see prepareAdminForm() docblock for
     * why this - and not Form::setValue() - is what actually shows up).
     *
     * IMPORTANT: different Joomla admin models represent `params` at this
     * point in the request differently - a Registry, a plain array/object,
     * or (commonly, before Form::bind() runs) still a raw JSON string.
     * Whichever shape it already has is preserved: a string stays a string
     * (decoded, merged, re-encoded), never coerced into a different type.
     * Replacing an unrecognized shape with a bare array would silently
     * destroy the original data and can break Joomla's own subsequent
     * binding/rendering (observed: this previously broke the Save/Close
     * toolbar entirely) - so an unrecognized/absent shape is left alone
     * (falls back to a plain array only when params is truly missing).
     *
     * @param object|array<string, mixed> $data
     * @param array<string, mixed>        $values
     */
    public static function applyValuesToData(&$data, array $values): void
    {
        $isDataArray = is_array($data);

        if (!$isDataArray && !is_object($data)) {
            return;
        }

        $current = $isDataArray ? ($data['params'] ?? null) : ($data->params ?? null);
        $updated = self::mergeIntoParams($current, $values);

        if ($updated === null) {
            return;
        }

        if ($isDataArray) {
            $data['params'] = $updated;
        } else {
            $data->params = $updated;
        }
    }

    /**
     * Merges $values into $current (whatever shape it already is) and
     * returns the result in that SAME shape. Returns null when $current's
     * shape is not one we recognize and not empty/absent, so the caller
     * leaves the original data untouched rather than guessing.
     *
     * @param array<string, mixed> $values
     * @return Registry|array<string, mixed>|object|string|null
     */
    public static function mergeIntoParams($current, array $values)
    {
        if ($current instanceof Registry) {
            foreach ($values as $key => $value) {
                $current->set((string) $key, $value);
            }

            return $current;
        }

        if (is_string($current)) {
            $decoded = $current === '' ? [] : json_decode($current, true);

            if (!is_array($decoded)) {
                $decoded = [];
            }

            foreach ($values as $key => $value) {
                $decoded[(string) $key] = $value;
            }

            return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_array($current)) {
            foreach ($values as $key => $value) {
                $current[(string) $key] = $value;
            }

            return $current;
        }

        if (is_object($current)) {
            foreach ($values as $key => $value) {
                $current->{$key} = $value;
            }

            return $current;
        }

        // Truly absent (null/unset): safe to create fresh as a plain array.
        if ($current === null) {
            return $values;
        }

        // Some other unrecognized, non-empty shape: don't guess, don't touch it.
        return null;
    }
}
