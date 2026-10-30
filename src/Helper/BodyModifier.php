<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @license     GNU General Public License version 2 or later
 *
 * Server-side body changes of the login page: custom logo and footer text.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Helper;

\defined('_JEXEC') or die;

final class BodyModifier
{
    /**
     * Returns the modified body, or null when nothing changed.
     *
     * @param string $logo     Absolute logo URL ('' = none)
     * @param string $logoDark Absolute dark-mode logo URL ('' = none)
     * @param string $footer   Trimmed footer HTML ('' = none)
     */
    public static function apply(string $body, string $logo, string $logoDark, string $footer): ?string
    {
        $changed = false;

        // ------------------------------------------------------------------
        // Custom logo
        // ------------------------------------------------------------------

        // Only a dark-mode logo set: use it for every color scheme.
        if ($logo === '') {
            $logo     = $logoDark;
            $logoDark = '';
        }

        if ($logo !== '') {
            $img = self::logoImgTag($logo, $logoDark !== '' ? 'alc-logo-light' : '');

            // Dark-mode variant: both images are rendered and CSS (see
            // buildLogoCss) shows the one matching Atum's color scheme.
            if ($logoDark !== '') {
                $img .= self::logoImgTag($logoDark, 'alc-logo-dark');
            }

            // Preferred: swap the content of the native Atum login-logo slot
            // (<div class="main-brand logo text-center"> above the form).
            // A balanced-tag scan (not a naive non-greedy regex) is used so
            // that a future template version nesting extra <div> markup
            // inside this slot still resolves to the correct closing tag
            // instead of truncating on the first </div> found.
            [$newBody, $count] = self::replaceBalancedDivContent(
                $body,
                '#<div\b[^>]*class="[^"]*\bmain-brand\b[^"]*\blogo\b[^"]*"[^>]*>#',
                $img
            );

            // Fallback: prepend the logo into the login box.
            if ($count === 0) {
                $newBody = preg_replace(
                    '#(<div\b[^>]*class="[^"]*\blogin\b[^"]*"[^>]*>)#',
                    '$1<div class="alc-logo">' . $img . '</div>',
                    $body,
                    1,
                    $count
                );
            }

            if ($count > 0 && \is_string($newBody)) {
                $body    = $newBody;
                $changed = true;
            }
        }

        // ------------------------------------------------------------------
        // Optional footer text below the login form ("Created by ...")
        // ------------------------------------------------------------------
        if ($footer !== '') {
            // Deliberately no .text-center class so the hide_forgot rule
            // (.login form ~ .text-center) cannot hide it.
            $html = '<div class="alc-footer">' . $footer . '</div>';

            // Escape backslashes and dollar signs so the admin-entered HTML
            // cannot act as backreferences in the replacement string.
            $replacement = strtr($html, ['\\' => '\\\\', '$' => '\\$']);

            $count   = 0;
            $newBody = preg_replace('#(</form>)#', '$1' . $replacement, $body, 1, $count);

            if ($count > 0 && \is_string($newBody)) {
                $body    = $newBody;
                $changed = true;
            }
        }

        return $changed ? $body : null;
    }

    /**
     * @param string $extraClass Additional CSS class (light/dark variant), may be empty.
     */
    private static function logoImgTag(string $url, string $extraClass): string
    {
        return '<img class="alc-logo-img' . ($extraClass !== '' ? ' ' . $extraClass : '')
            . '" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '" alt="" loading="eager" decoding="async">';
    }

    /**
     * Replaces the inner content of the first <div> matching $openTagPattern
     * with $newInnerHtml, resolving the correct matching closing </div> via
     * a depth-counting scan rather than a non-greedy regex. This stays
     * correct even if the div ever contains further nested <div> markup
     * (e.g. a future template wrapping the logo image), where a naive
     * `.*?</div>` regex would truncate on the first, wrong, closing tag.
     *
     * @return array{0: string, 1: int} [$newHtml, $replacementCount]
     */
    private static function replaceBalancedDivContent(string $html, string $openTagPattern, string $newInnerHtml): array
    {
        if (preg_match($openTagPattern, $html, $openMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return [$html, 0];
        }

        $openTagEnd = $openMatch[0][1] + \strlen($openMatch[0][0]);

        $depth    = 1;
        $cursor   = $openTagEnd;
        $closeAt  = null;
        $tagRegex = '#<div\b|</div>#i';

        while ($depth > 0) {
            if (preg_match($tagRegex, $html, $tagMatch, PREG_OFFSET_CAPTURE, $cursor) !== 1) {
                // Malformed/unbalanced markup: bail out without touching $html.
                return [$html, 0];
            }

            $tag    = $tagMatch[0][0];
            $tagPos = $tagMatch[0][1];
            $cursor = $tagPos + \strlen($tag);

            $depth += stripos($tag, '</div') === 0 ? -1 : 1;

            if ($depth === 0) {
                $closeAt = $tagPos;
            }
        }

        $newHtml = substr($html, 0, $openTagEnd) . $newInnerHtml . substr($html, $closeAt);

        return [$newHtml, 1];
    }
}
