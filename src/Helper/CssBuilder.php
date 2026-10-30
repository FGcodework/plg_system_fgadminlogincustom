<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @license     GNU General Public License version 2 or later
 *
 * Builds the inline CSS of the login page from the plugin parameters. No
 * Joomla dependency besides the Registry and the media URL helper.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Helper;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

final class CssBuilder
{
    private Registry $params;

    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    /**
     * Builds the inline CSS from the plugin parameters ('' when nothing is set).
     */
    public function build(): string
    {
        $btnColors = $this->resolveButtonColors();

        $css = array_merge(
            $this->buildBackgroundCss(),
            $this->buildChromeCss(),
            $this->buildSidebarCss(),
            $this->buildHeaderBarCss($btnColors),
            $this->buildLoginCardCss(),
            $this->buildControlShapeCss(),
            $this->buildButtonCss($btnColors),
            $this->buildBrandFocusCss(),
            $this->buildLogoCss(),
            $this->buildFooterCss(),
            $this->buildCustomCss()
        );

        return implode("\n", array_filter($css, static fn (string $rule): bool => $rule !== ''));
    }

    /**
     * Resolves the login button's background/hover/text colors once, so
     * both buildButtonCss() and buildHeaderBarCss() (header_match_btn) can
     * reuse them without recomputing. If a background is set but no text
     * color was chosen, a WCAG-contrasting text color is computed
     * automatically instead of leaving the text unstyled (which could be
     * unreadable against a dark custom background).
     *
     * @return array{bg: string, hover: string, text: string}
     */
    private function resolveButtonColors(): array
    {
        $brand = $this->brandColor();
        $bg    = ColorHelper::clean((string) $this->params->get('btn_bg', ''));
        $hover = ColorHelper::clean((string) $this->params->get('btn_hover', ''));
        $text  = ColorHelper::clean((string) $this->params->get('btn_text', ''));

        // Brand color fills in whatever the explicit fields leave empty.
        if ($bg === '' && $brand !== '') {
            $bg = $brand;

            if ($hover === '') {
                $hover = ColorHelper::shade($brand, -0.15);
            }
        }

        if ($text === '' && $bg !== '') {
            $text = ColorHelper::contrastText($bg);
        }

        return [
            'bg'    => $bg,
            'hover' => $hover,
            'text'  => $text,
        ];
    }

    /**
     * The single "brand color" (empty when unset/invalid). Used as the
     * fallback for the button, link and header colors; any explicitly set
     * field always wins over it.
     */
    private function brandColor(): string
    {
        return ColorHelper::clean((string) $this->params->get('brand_color', ''));
    }

    /**
     * Page background: solid color / gradient / image with overlay.
     *
     * @return string[]
     */
    private function buildBackgroundCss(): array
    {
        $bgType = (string) $this->params->get('bg_type', 'none');
        $bgRule = '';

        switch ($bgType) {
            case 'color':
                $color = ColorHelper::clean((string) $this->params->get('bg_color', ''));

                if ($color !== '') {
                    $bgRule = $color;
                }
                break;

            case 'gradient':
                $start = ColorHelper::clean((string) $this->params->get('grad_start', ''));
                $end   = ColorHelper::clean((string) $this->params->get('grad_end', ''));
                $angle = (int) $this->params->get('grad_angle', 135);

                if ($start !== '' && $end !== '') {
                    $bgRule = sprintf('linear-gradient(%ddeg, %s 0%%, %s 100%%)', $angle, $start, $end);
                }
                break;

            case 'image':
                $image = MediaUrl::clean((string) $this->params->get('bg_image', ''));

                if ($image !== '') {
                    $overlay        = ColorHelper::clean((string) $this->params->get('bg_overlay', '#000000'));
                    $overlayOpacity = (int) $this->params->get('bg_overlay_opacity', 40);
                    $overlayRgba    = ColorHelper::toRgba($overlay !== '' ? $overlay : '#000000', $overlayOpacity);

                    $bgRule = sprintf(
                        'linear-gradient(%1$s, %1$s), url("%2$s") center center / cover no-repeat',
                        $overlayRgba,
                        $image
                    );
                }
                break;
        }

        if ($bgRule === '') {
            return [];
        }

        return [
            'body.com_login, body.view-login { background: ' . $bgRule . ' !important;'
                . ' min-height: 100vh; }',
            '.view-login #wrapper, .view-login .container-main, .view-login #content,'
                . ' body.com_login #wrapper, body.com_login .container-main, body.com_login #content'
                . ' { background: transparent !important; }',
        ];
    }

    /**
     * Structural show/hide of template chrome: header logo/branding and the
     * optional full header+footer hide for a clean full-screen login.
     *
     * @return string[]
     */
    private function buildChromeCss(): array
    {
        $css = [];

        // Header (top-left) logo and legacy Joomla icon only. The native
        // login-logo slot (.login .main-brand) above the form is kept for
        // the custom logo.
        if ((int) $this->params->get('hide_template_logo', 1) === 1) {
            $css[] = '.view-login #header .logo, .view-login .navbar-brand, .view-login .login-joomla,'
                . ' body.com_login #header .logo, body.com_login .login-joomla'
                . ' { display: none !important; }';
        }

        if ((int) $this->params->get('hide_chrome', 0) === 1) {
            $css[] = '.view-login #header, .view-login .header, .view-login .subhead,'
                . ' .view-login #status, .view-login footer, .view-login .footer, .view-login #footer,'
                . ' body.com_login #header, body.com_login .header, body.com_login footer'
                . ' { display: none !important; }';
            $css[] = '.view-login #wrapper, body.com_login #wrapper { padding: 0 !important; }';
        }

        return $css;
    }

    /**
     * Left sidebar (template brand block + "Need Support?" module).
     *
     * @return string[]
     */
    private function buildSidebarCss(): array
    {
        if ((int) $this->params->get('hide_sidebar', 1) !== 1) {
            return [];
        }

        return [
            '.view-login #sidebar-wrapper, .view-login .sidebar-wrapper,'
                . ' body.com_login #sidebar-wrapper, body.com_login .sidebar-wrapper'
                . ' { display: none !important; }',
            // The wrapper is a flex container in Atum; without the sidebar
            // the content takes full width. Reset any explicit offset.
            '.view-login #content, body.com_login #content'
                . ' { margin-left: 0 !important; width: 100% !important; max-width: 100% !important; }',
        ];
    }

    /**
     * Header bar (top strip): background, text/icon color, language switcher,
     * and optionally matching the frontend-link button to the login button.
     *
     * @param array{bg: string, hover: string, text: string} $btnColors
     * @return string[]
     */
    private function buildHeaderBarCss(array $btnColors): array
    {
        $css = [];

        $headerBg   = ColorHelper::clean((string) $this->params->get('header_bg', ''));
        $headerText = ColorHelper::clean((string) $this->params->get('header_text', ''));

        if ($headerBg === '') {
            $headerBg = $this->brandColor();
        }

        if ($headerText === '' && $headerBg !== '') {
            $headerText = ColorHelper::contrastText($headerBg);
        }

        if ($headerBg !== '') {
            $css[] = '.view-login #header, body.com_login #header { background: ' . $headerBg . ' !important; }';
        }

        if ($headerText !== '') {
            $css[] = '.view-login #header, .view-login #header a, .view-login #header .header-item-text,'
                . ' .view-login #header .header-item-icon, .view-login #header .icon-angle-down,'
                . ' body.com_login #header, body.com_login #header a'
                . ' { color: ' . $headerText . ' !important; }';
        }

        // With a brand color the frontend-link pill (Atum's own blue) would
        // clash with the header; give it and its icon circle a translucent
        // tint of the header text color so it follows any brand color.
        // "Match login button" below takes over when it is enabled.
        if ($this->brandColor() !== '' && $headerBg !== ''
            && (int) $this->params->get('header_match_btn', 0) !== 1) {
            $onDark = $headerText === '#ffffff';

            $css[] = '.view-login #header a.header-item-content, body.com_login #header a.header-item-content'
                . ' { background-color: ' . ($onDark ? 'rgba(255, 255, 255, 0.16)' : 'rgba(0, 0, 0, 0.10)')
                . ' !important; }';
            $css[] = '.view-login #header a.header-item-content:hover, body.com_login #header a.header-item-content:hover'
                . ' { background-color: ' . ($onDark ? 'rgba(255, 255, 255, 0.26)' : 'rgba(0, 0, 0, 0.18)')
                . ' !important; }';
            $css[] = '.view-login #header a.header-item-content .header-item-icon > *,'
                . ' body.com_login #header a.header-item-content .header-item-icon > *'
                . ' { background-color: ' . ($onDark ? 'rgba(0, 0, 0, 0.28)' : 'rgba(255, 255, 255, 0.6)')
                . ' !important; }';
        }

        if ((int) $this->params->get('hide_lang_switcher', 0) === 1) {
            $css[] = '.view-login #header .mod-backendlangswitcher, body.com_login #header .mod-backendlangswitcher'
                . ' { display: none !important; }';
        }

        // Match the header "frontend link" button background/text to the
        // login button color. The anchor selector (a.header-item-content)
        // targets only the frontend link, not the language switcher wrapper
        // (which shares the .header-item-content class on a <div>).
        if ((int) $this->params->get('header_match_btn', 0) === 1 && $btnColors['bg'] !== '') {
            $rule = 'background-color: ' . $btnColors['bg'] . ' !important;'
                . ' border-color: ' . $btnColors['bg'] . ' !important;';

            if ($btnColors['text'] !== '') {
                $rule .= ' color: ' . $btnColors['text'] . ' !important;';
            }

            $css[] = '.view-login #header a.header-item-content,'
                . ' body.com_login #header a.header-item-content { ' . $rule . ' }';

            if ($btnColors['hover'] !== '') {
                $css[] = '.view-login #header a.header-item-content:hover,'
                    . ' body.com_login #header a.header-item-content:hover'
                    . ' { background-color: ' . $btnColors['hover'] . ' !important;'
                    . ' border-color: ' . $btnColors['hover'] . ' !important; }';
            }
        }

        return $css;
    }

    /**
     * Login card background/text/border-radius/shadow.
     *
     * @return string[]
     */
    private function buildLoginCardCss(): array
    {
        $css = [];

        $cardBg   = ColorHelper::clean((string) $this->params->get('card_bg', ''));
        $cardText = ColorHelper::clean((string) $this->params->get('card_text', ''));
        $cardRad  = $this->params->get('card_radius', '');
        $shadow   = (string) $this->params->get('card_shadow', 'none');

        if ($cardText === '' && $cardBg !== '') {
            $cardText = ColorHelper::contrastText($cardBg);
        }

        if ($cardBg !== '') {
            $css[] = '.view-login .login, .view-login .card, .view-login .card-body, .view-login .card-header,'
                . ' body.com_login .login, body.com_login .card { background: ' . $cardBg
                . ' !important; border-color: transparent !important; }';
        }

        if ($cardText !== '') {
            $css[] = '.view-login .login, .view-login .login label, .view-login .login .form-label,'
                . ' .view-login .login legend, .view-login .card, .view-login .card label,'
                . ' body.com_login .login, body.com_login .card'
                . ' { color: ' . $cardText . ' !important; }';
        }

        if ($cardRad !== '' && $cardRad !== null) {
            $css[] = '.view-login .login, .view-login .card, body.com_login .login, body.com_login .card'
                . ' { border-radius: ' . (int) $cardRad . 'px !important; overflow: hidden; }';
        }

        $shadowValues = [
            'subtle' => '0 1px 3px rgba(0, 0, 0, 0.15)',
            'medium' => '0 4px 12px rgba(0, 0, 0, 0.18)',
            'strong' => '0 12px 32px rgba(0, 0, 0, 0.28)',
        ];

        if (isset($shadowValues[$shadow])) {
            $css[] = '.view-login .login, .view-login .card, body.com_login .login, body.com_login .card'
                . ' { box-shadow: ' . $shadowValues[$shadow] . ' !important; }';
        }

        return $css;
    }

    /**
     * Corner radius for inputs and buttons and a shadow preset for buttons.
     * Inputs here are Bootstrap input groups (field + icon/toggle button), so
     * only the outer corners of a group are rounded and the joint stays flat.
     *
     * @return string[]
     */
    private function buildControlShapeCss(): array
    {
        $css    = [];
        $radius = trim((string) $this->params->get('ctrl_radius', ''));
        $shadow = (string) $this->params->get('btn_shadow', 'none');

        if ($radius !== '' && is_numeric($radius)) {
            $r = max(0, min(40, (int) $radius)) . 'px';

            $css[] = $this->scoped('.login .form-control, .login .form-select, .login .input-group-text, .login .btn')
                . ' { border-radius: ' . $r . ' !important; }';
            $css[] = $this->scoped('.login .input-group > :not(:first-child)')
                . ' { border-start-start-radius: 0 !important; border-end-start-radius: 0 !important; }';
            $css[] = $this->scoped('.login .input-group > :not(:last-child)')
                . ' { border-start-end-radius: 0 !important; border-end-end-radius: 0 !important; }';
        }

        $shadowValues = [
            'subtle' => '0 1px 2px rgba(0, 0, 0, 0.2)',
            'medium' => '0 3px 8px rgba(0, 0, 0, 0.22)',
            'strong' => '0 6px 16px rgba(0, 0, 0, 0.3)',
        ];

        if (isset($shadowValues[$shadow])) {
            $css[] = $this->scoped('.login .btn-primary, .login .btn-secondary')
                . ' { box-shadow: ' . $shadowValues[$shadow] . ' !important; }';
        }

        return $css;
    }

    /**
     * Keeps the focus ring of the login fields in the brand color, so a
     * single brand color is not undermined by Bootstrap's default blue ring.
     *
     * @return string[]
     */
    private function buildBrandFocusCss(): array
    {
        $brand = $this->brandColor();

        if ($brand === '') {
            return [];
        }

        return [
            $this->scoped('.login .form-control:focus, .login .form-select:focus')
                . ' { border-color: ' . $brand . ' !important;'
                . ' box-shadow: 0 0 0 0.2rem ' . ColorHelper::toRgba($brand, 25) . ' !important; }',
        ];
    }

    /**
     * Login/passkey buttons and generic link color.
     *
     * @param array{bg: string, hover: string, text: string} $btnColors
     * @return string[]
     */
    private function buildButtonCss(array $btnColors): array
    {
        $css = [];

        if ($btnColors['bg'] !== '') {
            $rule = 'background-color: ' . $btnColors['bg'] . ' !important;'
                . ' border-color: ' . $btnColors['bg'] . ' !important;';

            if ($btnColors['text'] !== '') {
                $rule .= ' color: ' . $btnColors['text'] . ' !important;';
            }

            $css[] = '.view-login .btn-primary, .view-login .btn-secondary,'
                . ' body.com_login .btn-primary, body.com_login .btn-secondary { ' . $rule . ' }';
        }

        if ($btnColors['hover'] !== '') {
            $css[] = '.view-login .btn-primary:hover, .view-login .btn-primary:focus,'
                . ' .view-login .btn-primary:active, .view-login .btn-secondary:hover,'
                . ' .view-login .btn-secondary:focus, .view-login .btn-secondary:active,'
                . ' body.com_login .btn-primary:hover, body.com_login .btn-secondary:hover'
                . ' { background-color: ' . $btnColors['hover'] . ' !important;'
                . ' border-color: ' . $btnColors['hover'] . ' !important; }';
        }

        $linkColor = ColorHelper::clean((string) $this->params->get('link_color', ''));

        // Brand color as link color only when it stays readable (WCAG 3:1,
        // the large-text/UI threshold) on the card background.
        if ($linkColor === '' && $this->brandColor() !== '') {
            $cardBg = ColorHelper::clean((string) $this->params->get('card_bg', '')) ?: '#ffffff';

            if (ColorHelper::contrast($this->brandColor(), $cardBg) >= 3.0) {
                $linkColor = $this->brandColor();
            }
        }

        if ($linkColor !== '') {
            $css[] = '.view-login a:not(.btn), body.com_login a:not(.btn) { color: ' . $linkColor . ' !important; }';
        }

        return $css;
    }

    /**
     * Custom logo container: centering + sizing. When "Logo height" is left
     * empty (default), the image scales freely by its aspect ratio via
     * max-width (never distorted). When a height is set, the image gets a
     * fixed width x height box and object-fit decides how it fills it.
     *
     * @return string[]
     */
    private function buildLogoCss(): array
    {
        $logoWidth  = max(40, (int) $this->params->get('logo_width', 220));
        $logoHeight = trim((string) $this->params->get('logo_height', ''));
        $logoFit    = (string) $this->params->get('logo_fit', 'contain');
        $logoFit    = $logoFit === 'cover' ? 'cover' : 'contain';

        if ($logoHeight !== '' && (int) $logoHeight > 0) {
            $imgRule = 'width: ' . $logoWidth . 'px !important; height: ' . (int) $logoHeight
                . 'px !important; object-fit: ' . $logoFit . '; object-position: center;';
        } else {
            $imgRule = 'max-width: ' . $logoWidth . 'px; width: auto !important; height: auto !important;';
        }

        $css = [
            '.view-login .alc-logo, body.com_login .alc-logo, .view-login .login .main-brand'
                . ' { display: flex !important; justify-content: center !important; align-items: center;'
                . ' width: 100% !important; margin: 0 auto 1.5rem !important; text-align: center !important;'
                . ' float: none !important; }',
            '.alc-logo img, .alc-logo-img, .view-login .login .main-brand img'
                . ' { display: block !important; margin: 0 auto !important; ' . $imgRule . ' }',
        ];

        if ((string) $this->params->get('logo', '') !== '' && (string) $this->params->get('logo_dark', '') !== '') {
            // Atum color scheme: explicit dark = html[data-color-scheme="dark"];
            // "follow OS" = html[data-color-scheme-os] + prefers-color-scheme
            // (the explicit attribute only appears after Atum's script ran).
            // "html:root body" keeps the specificity above the display rule
            // of the generic logo selector.
            $img   = 'html:root body img.alc-logo-img';
            $css[] = $img . '.alc-logo-dark { display: none !important; }';
            $css[] = 'html:root[data-color-scheme="dark"] body img.alc-logo-img.alc-logo-dark'
                . ' { display: block !important; }';
            $css[] = 'html:root[data-color-scheme="dark"] body img.alc-logo-img.alc-logo-light'
                . ' { display: none !important; }';
            $css[] = '@media (prefers-color-scheme: dark) {'
                . ' html:root[data-color-scheme-os]:not([data-color-scheme="light"]) body img.alc-logo-img.alc-logo-dark'
                . ' { display: block !important; }'
                . ' html:root[data-color-scheme-os]:not([data-color-scheme="light"]) body img.alc-logo-img.alc-logo-light'
                . ' { display: none !important; } }';
        }

        return $css;
    }

    /**
     * Area below the login form: hides the native "Forgot your login
     * details?" link and styles the optional custom footer text.
     *
     * @return string[]
     */
    private function buildFooterCss(): array
    {
        $css = [];

        if ((int) $this->params->get('hide_forgot', 1) === 1) {
            // Only .text-center blocks AFTER the login form (the recovery
            // link). The logo slot also carries .text-center but sits BEFORE
            // the form, so the sibling combinator can never match it.
            $css[] = '.view-login .login form ~ .text-center,'
                . ' body.com_login .login form ~ .text-center'
                . ' { display: none !important; }';
        }

        $css[] = '.alc-footer { text-align: center; margin-top: 1.25rem; font-size: 0.875rem;'
            . ' opacity: 0.85; }';

        return $css;
    }

    /**
     * Raw custom CSS from the Advanced field, appended last so it wins.
     *
     * @return string[]
     */
    private function buildCustomCss(): array
    {
        $customCss = trim((string) $this->params->get('custom_css', ''));

        return $customCss !== '' ? [$customCss] : [];
    }

    /**
     * Prefixes each selector of a comma-separated list with both login-page
     * scopes used by Atum (.view-login and body.com_login).
     */
    private function scoped(string $selectors): string
    {
        $out = [];

        foreach (explode(',', $selectors) as $selector) {
            $selector = trim($selector);

            if ($selector !== '') {
                $out[] = '.view-login ' . $selector;
                $out[] = 'body.com_login ' . $selector;
            }
        }

        return implode(', ', $out);
    }
}
