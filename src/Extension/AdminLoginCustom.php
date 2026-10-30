<?php

/**
 * @package     plg_system_fgadminlogincustom
 * @version     1.19.3
 * @license     GNU General Public License version 2 or later
 *
 * Visual customization of the /administrator login page (Atum template, Joomla 4/5/6).
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminLoginCustom\Extension;

use FG\Plugin\System\AdminLoginCustom\Helper\BodyModifier;
use FG\Plugin\System\AdminLoginCustom\Helper\CssBuilder;
use FG\Plugin\System\AdminLoginCustom\Helper\ImportExport;
use FG\Plugin\System\AdminLoginCustom\Helper\MediaUrl;
use FG\Plugin\System\AdminLoginCustom\Helper\SettingsSanitizer;
use FG\Plugin\System\AdminLoginCustom\Helper\ThemePresets;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

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
     * Recovery switch: opening the login page with ?fgalc=off skips all
     * visual customization for that one request (nothing is saved or
     * changed), so a custom CSS/JS rule that broke the login form can never
     * lock anyone out. The generator tag stays hidden either way.
     */
    private function isSafeMode(): bool
    {
        return $this->getApplication()->getInput()->getCmd('fgalc', '') === 'off';
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

        if ($this->isSafeMode()) {
            return;
        }

        $wa = $doc->getWebAssetManager();

        $css = (new CssBuilder($this->params))->build();

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
        if (!$this->isLoginPage() || $this->isSafeMode()) {
            return;
        }

        $app  = $this->getApplication();
        $body = $app->getBody();

        if (!\is_string($body) || $body === '') {
            return;
        }

        $newBody = BodyModifier::apply(
            $body,
            MediaUrl::clean((string) $this->params->get('logo', '')),
            MediaUrl::clean((string) $this->params->get('logo_dark', '')),
            trim((string) $this->params->get('footer_text', ''))
        );

        if ($newBody !== null) {
            $app->setBody($newBody);
        }
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

        $params = ImportExport::paramsToArray($paramsData);

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
            $presets = ThemePresets::all();

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

                // Only this plugin's own settings are accepted, each one validated.
                $result   = SettingsSanitizer::sanitize($imported);
                $updates += $result['values'];
                $updates['import_json'] = '';
                $this->getApplication()->enqueueMessage(
                    Text::_('PLG_SYSTEM_FGADMINLOGINCUSTOM_IMPORT_APPLIED'),
                    'message'
                );

                if ($result['rejected'] !== []) {
                    $this->getApplication()->enqueueMessage(
                        Text::sprintf('PLG_SYSTEM_FGADMINLOGINCUSTOM_IMPORT_SKIPPED', implode(', ', $result['rejected'])),
                        'warning'
                    );
                }

                if ($skippedJs) {
                    $this->getApplication()->enqueueMessage(
                        Text::_('PLG_SYSTEM_FGADMINLOGINCUSTOM_IMPORT_JS_SKIPPED'),
                        'warning'
                    );
                }
            }
        }

        ImportExport::applyValuesToData($data, $updates);
    }

}
