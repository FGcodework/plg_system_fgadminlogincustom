<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @version     1.19.1
 * @license     GNU General Public License version 2 or later
 *
 * Visual customization of the /administrator login page (Atum template, Joomla 4/5/6).
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Extension;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

final class AdminLoginCustom extends CMSPlugin implements SubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead'  => 'injectAssets',
            'onAfterRender'        => 'modifyBody',
            'onContentPrepareForm' => 'prepareAdminForm',
            // Re-enabled in 1.16.4 after confirming the real root cause of
            // the broken Save/Close toolbar was a literal "<script>" in a
            // field description string (fixed in 1.16.3), not this handler
            // - see CHANGELOG 1.16.2/1.16.3 for the full investigation.
        ];
    }

    /**
     * Whether the current request is the administrator login page (guest).
     */
    private function isLoginPage(): bool
    {
        $app = $this->getApplication();

        if (!$app instanceof CMSApplicationInterface || !$app->isClient('administrator')) {
            return false;
        }

        // Only for guests (login page / expired session login).
        $identity = $app->getIdentity();

        if ($identity !== null && !$identity->guest) {
            return false;
        }

        // Login page is com_login (or empty option before routing).
        $option = $app->getInput()->getCmd('option', '');

        return $option === '' || $option === 'com_login';
    }

    /**
     * Injects inline CSS/JS into the administrator login page only.
     */
    public function injectAssets(Event $event): void
    {
        if (!$this->isLoginPage()) {
            return;
        }

        $doc = $this->getApplication()->getDocument();

        if (!$doc instanceof HtmlDocument) {
            return;
        }

        // Do not reveal the CMS in <meta name="generator">.
        if ((int) $this->params->get('hide_generator', 1) === 1) {
            $doc->setGenerator('');
        }

        $wa = $doc->getWebAssetManager();

        $css = $this->buildCss();

        if ($css !== '') {
            $wa->addInlineStyle($css, ['name' => 'plg.system.fgadminlogincustom.style']);
        }

        $js = trim((string) $this->params->get('custom_js', ''));

        if ($js !== '') {
            $wa->addInlineScript($js, ['name' => 'plg.system.fgadminlogincustom.customjs'], ['defer' => true]);
        }
    }

    /**
     * Server-side body modifications (no JS = no flicker): replaces the
     * template login logo with the custom one and appends the optional
     * footer text below the login form.
     */
    public function modifyBody(Event $event): void
    {
        if (!$this->isLoginPage()) {
            return;
        }

        $app  = $this->getApplication();
        $body = $app->getBody();

        if (!\is_string($body) || $body === '') {
            return;
        }

        $changed = false;

        // ------------------------------------------------------------------
        // Custom logo
        // ------------------------------------------------------------------
        $logo     = $this->cleanMediaUrl((string) $this->params->get('logo', ''));
        $logoDark = $this->cleanMediaUrl((string) $this->params->get('logo_dark', ''));

        // Only a dark-mode logo set: use it for every color scheme.
        if ($logo === '') {
            $logo     = $logoDark;
            $logoDark = '';
        }

        if ($logo !== '') {
            $img = $this->logoImgTag($logo, $logoDark !== '' ? 'alc-logo-light' : '');

            // Dark-mode variant: both images are rendered and CSS (see
            // buildLogoCss) shows the one matching Atum's color scheme.
            if ($logoDark !== '') {
                $img .= $this->logoImgTag($logoDark, 'alc-logo-dark');
            }

            // Preferred: swap the content of the native Atum login-logo slot
            // (<div class="main-brand logo text-center"> above the form).
            // A balanced-tag scan (not a naive non-greedy regex) is used so
            // that a future template version nesting extra <div> markup
            // inside this slot still resolves to the correct closing tag
            // instead of truncating on the first </div> found.
            [$newBody, $count] = $this->replaceBalancedDivContent(
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
        $footer = trim((string) $this->params->get('footer_text', ''));

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

        if ($changed) {
            $app->setBody($body);
        }
    }

    /**
     * @param string $extraClass Additional CSS class (light/dark variant), may be empty.
     */
    private function logoImgTag(string $url, string $extraClass): string
    {
        return '<img class="alc-logo-img' . ($extraClass !== '' ? ' ' . $extraClass : '')
            . '" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '" alt="" loading="eager" decoding="async">';
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

    /**
     * Builds the inline CSS from plugin parameters.
     */
    private function buildCss(): string
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
        $bg    = $this->cleanColor((string) $this->params->get('btn_bg', ''));
        $hover = $this->cleanColor((string) $this->params->get('btn_hover', ''));
        $text  = $this->cleanColor((string) $this->params->get('btn_text', ''));

        // Brand color fills in whatever the explicit fields leave empty.
        if ($bg === '' && $brand !== '') {
            $bg = $brand;

            if ($hover === '') {
                $hover = $this->shadeColor($brand, -0.15);
            }
        }

        if ($text === '' && $bg !== '') {
            $text = $this->autoContrastColor($bg);
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
        return $this->cleanColor((string) $this->params->get('brand_color', ''));
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
                $color = $this->cleanColor((string) $this->params->get('bg_color', ''));

                if ($color !== '') {
                    $bgRule = $color;
                }
                break;

            case 'gradient':
                $start = $this->cleanColor((string) $this->params->get('grad_start', ''));
                $end   = $this->cleanColor((string) $this->params->get('grad_end', ''));
                $angle = (int) $this->params->get('grad_angle', 135);

                if ($start !== '' && $end !== '') {
                    $bgRule = sprintf('linear-gradient(%ddeg, %s 0%%, %s 100%%)', $angle, $start, $end);
                }
                break;

            case 'image':
                $image = $this->cleanMediaUrl((string) $this->params->get('bg_image', ''));

                if ($image !== '') {
                    $overlay        = $this->cleanColor((string) $this->params->get('bg_overlay', '#000000'));
                    $overlayOpacity = (int) $this->params->get('bg_overlay_opacity', 40);
                    $overlayRgba    = $this->hexToRgba($overlay !== '' ? $overlay : '#000000', $overlayOpacity);

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

        $headerBg   = $this->cleanColor((string) $this->params->get('header_bg', ''));
        $headerText = $this->cleanColor((string) $this->params->get('header_text', ''));

        if ($headerBg === '') {
            $headerBg = $this->brandColor();
        }

        if ($headerText === '' && $headerBg !== '') {
            $headerText = $this->autoContrastColor($headerBg);
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

        $cardBg   = $this->cleanColor((string) $this->params->get('card_bg', ''));
        $cardText = $this->cleanColor((string) $this->params->get('card_text', ''));
        $cardRad  = $this->params->get('card_radius', '');
        $shadow   = (string) $this->params->get('card_shadow', 'none');

        if ($cardText === '' && $cardBg !== '') {
            $cardText = $this->autoContrastColor($cardBg);
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
                . ' box-shadow: 0 0 0 0.2rem ' . $this->hexToRgba($brand, 25) . ' !important; }',
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

        $linkColor = $this->cleanColor((string) $this->params->get('link_color', ''));

        // Brand color as link color only when it stays readable (WCAG 3:1,
        // the large-text/UI threshold) on the card background.
        if ($linkColor === '' && $this->brandColor() !== '') {
            $cardBg = $this->cleanColor((string) $this->params->get('card_bg', '')) ?: '#ffffff';

            if ($this->contrastRatio($this->brandColor(), $cardBg) >= 3.0) {
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
     * Populates the read-only "Export" field with the plugin's current
     * settings as JSON, and applies a previously pasted+saved "Import" JSON
     * (or a chosen theme preset) onto the other fields' displayed values
     * (the admin still needs to hit Save again to persist the applied
     * values).
     *
     * IMPORTANT: values are written onto the `data` argument, NOT via
     * `$form->setValue()`. onContentPrepareForm fires while the form is
     * being loaded, and the caller binds `$data` onto the form immediately
     * afterwards - that later bind() silently overwrites anything set
     * directly on the Form object, so Form::setValue() calls here have no
     * visible effect even though they run without error. Writing onto
     * `$data` (an object, mutated in place) is the documented, reliable way
     * to influence what actually gets displayed.
     *
     * Deliberately does NOT use onExtensionBeforeSave: that event has a long
     * history of not firing reliably for plugin saves specifically (a core
     * Joomla issue still being addressed as of 6.1), so a one-shot "paste and
     * Save" import could silently do nothing. onContentPrepareForm has no
     * such history and fires on every edit-screen load/reload, which is what
     * "Save" (without Close) naturally triggers - so paste -> Save -> the
     * fields refresh with imported values -> Save again to commit.
     */
    public function prepareAdminForm(Event $event): void
    {
        try {
            $this->doPrepareAdminForm($event);
        } catch (\Throwable $e) {
            // Never let an unexpected data shape or error here break the
            // admin page around it (this previously happened when a
            // destructive fallback clobbered an unrecognized $data->params
            // representation - see mergeIntoParams()). Fail silently rather
            // than risk corrupting page rendering / the Save toolbar.
            return;
        }
    }

    /**
     * @see prepareAdminForm()
     */
    private function doPrepareAdminForm(Event $event): void
    {
        $form = $event->getArgument('form');
        $data = $event->getArgument('data');

        if (!$form instanceof Form || $form->getName() !== 'com_plugins.plugin') {
            return;
        }

        $isDataArray = is_array($data);
        $element     = $isDataArray ? ($data['element'] ?? '') : ($data->element ?? '');
        $folder      = $isDataArray ? ($data['folder'] ?? '') : ($data->folder ?? '');

        if ($element !== 'fgadminlogincustom' || $folder !== 'system') {
            return;
        }

        $paramsData = $isDataArray ? ($data['params'] ?? null) : ($data->params ?? null);

        if ($paramsData instanceof Registry) {
            $params = $paramsData->toArray();
        } elseif (is_string($paramsData)) {
            $decoded = $paramsData === '' ? [] : json_decode($paramsData, true);
            $params  = is_array($decoded) ? $decoded : [];
        } elseif (is_object($paramsData)) {
            $params = (array) $paramsData;
        } elseif (is_array($paramsData)) {
            $params = $paramsData;
        } else {
            $params = [];
        }

        // ------------------------------------------------------------------
        // Export: always reflect the currently stored settings.
        // ------------------------------------------------------------------
        $exportable = $params;
        unset($exportable['export_json'], $exportable['import_json']);
        ksort($exportable);

        $updates = [
            'export_json' => json_encode(
                $exportable,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
        ];

        // ------------------------------------------------------------------
        // Theme preset: a curated color combination applied in one go.
        // ------------------------------------------------------------------
        $preset = trim((string) ($params['theme_preset'] ?? ''));

        if ($preset !== '') {
            $presets = $this->getThemePresets();

            if (isset($presets[$preset])) {
                $updates += $presets[$preset];
                $this->getApplication()->enqueueMessage(
                    Text::_('PLG_SYSTEM_FGADMINLOGINCUSTOM_PRESET_APPLIED'),
                    'message'
                );
            }

            $updates['theme_preset'] = '';
        }

        // ------------------------------------------------------------------
        // Import: if a JSON blob was pasted and saved last time, apply it
        // onto the other fields now and clear the field so it does not
        // reapply forever.
        // ------------------------------------------------------------------
        $importRaw = trim((string) ($params['import_json'] ?? ''));

        if ($importRaw !== '') {
            $imported = json_decode($importRaw, true);

            if (!is_array($imported) || json_last_error() !== JSON_ERROR_NONE) {
                $this->getApplication()->enqueueMessage(
                    Text::_('PLG_SYSTEM_FGADMINLOGINCUSTOM_IMPORT_INVALID_JSON'),
                    'warning'
                );
                $updates['import_json'] = '';
            } else {
                unset($imported['export_json'], $imported['import_json']);

                // Never let imported JSON carry executable code into the
                // login page. An import is typically a settings blob passed
                // between people/sites, so the admin pasting it has usually
                // not read every value in it - and custom_js runs on the
                // login screen, where the username/password fields live.
                // Every other setting is declarative and safe to carry over.
                $skippedJs = array_key_exists('custom_js', $imported);
                unset($imported['custom_js']);

                $updates += $imported;
                $updates['import_json'] = '';
                $this->getApplication()->enqueueMessage(
                    Text::_('PLG_SYSTEM_FGADMINLOGINCUSTOM_IMPORT_APPLIED'),
                    'message'
                );

                if ($skippedJs) {
                    $this->getApplication()->enqueueMessage(
                        Text::_('PLG_SYSTEM_FGADMINLOGINCUSTOM_IMPORT_JS_SKIPPED'),
                        'warning'
                    );
                }
            }
        }

        $this->applyValuesToData($data, $updates);
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
    private function applyValuesToData(&$data, array $values): void
    {
        $isDataArray = is_array($data);

        if (!$isDataArray && !is_object($data)) {
            return;
        }

        $current = $isDataArray ? ($data['params'] ?? null) : ($data->params ?? null);
        $updated = $this->mergeIntoParams($current, $values);

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
    private function mergeIntoParams($current, array $values)
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

    /**
     * Built-in color presets. Deliberately touch only color/background
     * fields, never layout/privacy toggles (hide_sidebar, hide_chrome, ...)
     * so applying a preset never silently changes structural behaviour the
     * admin configured separately. "reset" clears overrides back to the
     * Atum template's own colors.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getThemePresets(): array
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
    private function replaceBalancedDivContent(string $html, string $openTagPattern, string $newInnerHtml): array
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

    /**
     * Strips the #joomlaImage adapter fragment from a media field value
     * and returns an absolute URL, or an empty string.
     */
    private function cleanMediaUrl(string $value): string
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

    /**
     * Validates a CSS color value coming from the color field (hex) — defensive filter.
     */
    private function cleanColor(string $value): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) !== 1) {
            return '';
        }

        return strtolower($value);
    }

    /**
     * Picks the more readable of pure black/white against a given hex
     * background color, using the WCAG relative luminance formula and
     * comparing the actual contrast ratio against both candidates (rather
     * than a fixed brightness threshold) - the standard, robust approach.
     */
    private function autoContrastColor(string $hexBg): string
    {
        $luminance = $this->relativeLuminance($hexBg);

        $contrastWithWhite = 1.05 / ($luminance + 0.05);
        $contrastWithBlack = ($luminance + 0.05) / 0.05;

        return $contrastWithWhite >= $contrastWithBlack ? '#ffffff' : '#000000';
    }

    /**
     * WCAG relative luminance (0..1) of a #rgb / #rrggbb / #rrggbbaa color.
     */
    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = $this->hexToRgb($hex);

        $linearize = static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $linearize($r) + 0.7152 * $linearize($g) + 0.0722 * $linearize($b);
    }

    /**
     * WCAG contrast ratio (1..21) between two hex colors.
     */
    private function contrastRatio(string $hexA, string $hexB): float
    {
        $a = $this->relativeLuminance($hexA);
        $b = $this->relativeLuminance($hexB);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Darkens (negative $amount) or lightens (positive) a color by mixing it
     * with black or white; alpha channel, if any, is dropped.
     */
    private function shadeColor(string $hex, float $amount): string
    {
        $target = $amount < 0 ? 0 : 255;
        $weight = min(1.0, abs($amount));
        $out    = '#';

        foreach ($this->hexToRgb($hex) as $channel) {
            $out .= sprintf('%02x', (int) round($channel + ($target - $channel) * $weight));
        }

        return $out;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
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
     * Converts a hex color + opacity percentage to an rgba() string.
     */
    private function hexToRgba(string $hex, int $opacityPercent): string
    {
        $hex = ltrim($hex, '#');

        if (\strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $alpha = max(0, min(100, $opacityPercent)) / 100;

        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $alpha);
    }
}
