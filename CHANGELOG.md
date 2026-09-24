# Changelog - plg_system_fgadminlogincustom

## 1.18.2 (2026-08-04)
- FIX: v1.18.1 merged the "Support this plugin" note into the same fieldset as the logo/layout settings, so both showed mixed together under the native "Plugin" tab - not the intended layout. Moved the note into its own dedicated fieldset ("Support"), now the first tab after Joomla's native "Plugin" tab; the logo/layout settings are back on their own, unchanged, exactly as before v1.18.1. Total tab count: the original 7 settings tabs are untouched, plus this one new "Support" tab.

## 1.18.1 (2026-08-04)
- Added a "Support this plugin" note to the Plugin Options screen (merged into Joomla's native "Plugin" tab, same FG series convention as plg_system_fgemailremover): free/open-source statement, an optional Ko-fi link, and a link to fgcodework.github.io for the rest of the FG extensions. No functional change to the plugin itself.
- Added .github/FUNDING.yml (Ko-fi) and a matching "Support this project" section + badge in README.md.

## 1.18.0 (2026-08-04)
- SECURITY: Import now deliberately never carries the `custom_js` value over. A settings JSON is something people pass between sites and colleagues, so the admin pasting it has usually not read every value in it - and `custom_js` executes on the login page, alongside the username and password inputs. Every other setting is declarative and is imported as before. If the JSON contained a Custom JavaScript value, a warning message explains that it was skipped and that it can be entered manually after review.
- Export still includes `custom_js`, so a deliberate, reviewed transfer by hand remains possible.
- README: added a short section explaining what the Custom JavaScript field does, why Import skips it, and the fact that on a stock Joomla setup only a Super User reaches these settings (where the field grants no privilege they do not already have) - the caution applies to settings arriving from elsewhere, or to a delegated `com_plugins` permission.
- Considered and deliberately NOT done: sanitising/stripping the Custom CSS, Custom JavaScript or Footer text fields (it would remove the very feature those fields exist for), and a Content Security Policy on the admin login page (Joomla's own admin pages rely on inline scripts, so a CSP strict enough to matter would break the page).
- Verified with 9 tests: foreign `custom_js` is not carried over while all other imported values are, the existing local value survives an import, no spurious warning when the JSON has no JS, an empty imported JS value does not overwrite a local one, and the export still contains `custom_js`.

## 1.17.0 (2026-08-04)
- PHP namespace and class name aligned with the FG extension series convention (following `plg_system_fgstripcomments`): `Fero\Plugin\System\FgAdminLoginCustom\Extension\FgAdminLoginCustom` → `FG\Plugin\System\AdminLoginCustom\Extension\AdminLoginCustom`. The class file was renamed to `src/Extension/AdminLoginCustom.php`.
- The Joomla `element` (`fgadminlogincustom`), folder name, language keys and display name are unchanged - from Joomla's point of view this is a regular update, not a new plugin. No functional change.
- Added `<authorUrl>` and `<updateservers>` to the manifest - Joomla now reports new versions directly from the GitHub repository (`updates.xml`).
- Added files for the GitHub release: `README.md`, `LICENSE` (GPL-2.0), `.gitignore`, `updates.xml`.
- Verified with the full test suite after the refactor (buildCss, export/import, presets, custom JS, shadows, contrast) - everything behaves exactly as before.

## 1.16.5 (2026-08-04)
- Plugin display name in the plugin list aligned with the new identity: "System - Admin Login Customizer" → "System - FG Admin Login Customizer" (en-GB), and the corresponding sk-SK name. Changed in both `.ini` and `.sys.ini` for both languages (`.sys.ini` controls the name shown in the Extensions list before installation / during discovery).

## 1.16.4 (2026-08-03)
- Confirmed on the affected site: the language-string fix in 1.16.3 (removing the literal `<script>` text) restored the Save / Save & Close / Close buttons.
- `onContentPrepareForm` (Export/Import/Preset field auto-fill) RE-ENABLED - it was a false lead; the real cause was the language string, not this logic (see 1.16.2/1.16.3 for the full investigation).
- Behaviour is now identical to v1.16.1 (type-preserving `$data->params` mutation, `try/catch` safety net), just without the problematic `<script>` phrase in a field description.

## 1.16.3 (2026-08-03) - LIKELY ROOT CAUSE FOUND
- Confirmed by bisecting: v1.15.1 worked, v1.16.0 (which added the "Custom JavaScript" field) broke Save/Close. Since the problem persisted even with the PHP logic fully disabled (v1.16.2), the cause had to be in the STATIC XML/language declaration, not in runtime code.
- FOUND: the language string `PLG_SYSTEM_FGADMINLOGINCUSTOM_CUSTOM_JS_DESC` (a field description, rendered as a tooltip) contained a literal script tag in its text. Joomla admin pages embed field description text into an HTML/JS context in a way that apparently does not escape this sequence reliably - the result was a corrupted HTML structure for the whole form (missing `task` input → `Uncaught TypeError: Cannot set properties of undefined` on Save/Close).
- FIX: the description was rephrased without literal angle brackets ("Do not wrap it in a script element...").
- All other language strings and XML descriptions/labels were searched for the same pattern - no further occurrence found.
- `onContentPrepareForm` (Export/Import/Preset) REMAINS DISABLED from v1.16.2 for now - deliberately, so this fix could be verified in isolation as the only change before adding another layer back.

## 1.16.2 (2026-08-03) - STABILISATION RELEASE
- CRITICAL: a broken plugin edit screen was reported - Save / Save & Close / Close did nothing and the last tab was missing. The browser console showed `Uncaught TypeError: Cannot set properties of undefined (setting 'value') at Joomla.submitform` - a symptom of a corrupted form HTML structure (missing hidden `task` input).
- Suspicion: the `export_json` field injected a large, dynamically assembled JSON blob into the page (including values such as `footer_text` containing HTML links) - this probably broke the form's HTML structure while rendering the `readonly` textarea field.
- IMMEDIATE REMEDY (stabilisation): the `onContentPrepareForm` subscription (and with it the entire Export/Import/Preset auto-fill logic) is TEMPORARILY DISABLED COMPLETELY. This is guaranteed to restore Save/Close/Apply, since Joomla now renders the fields the standard way without any intervention from this plugin.
- CONSEQUENCE: the "Preset" and "Export / Import" tabs DO NOT WORK in this version (the dropdown/textarea fields are visible, but selecting a preset or pasting JSON applies nothing). All other features (logo, background, colors, shadows, custom CSS/JS on the login page) are fully functional and untouched by this change.
- This release is deliberately conservative after two unsuccessful remote fix attempts - the goal is to restore stability first; Export/Import/Preset will be reworked and re-tested against the affected environment in a separate follow-up release.

## 1.16.1 (2026-08-03)
- FIX (critical): the Save / Save & Close / Close buttons on the plugin edit screen stopped working and the Export/Import tab disappeared. Cause: `applyValuesToData()` contained a fallback that, whenever `$data->params` was something other than an array/object (typically a **raw JSON string** - which is how the `com_plugins` model returns it BEFORE Joomla calls `$form->bind()`), **replaced that string with an empty array**, destroying all original data before the form was even processed. This most likely caused a fatal error later in Joomla's `bind()` step, corrupting page rendering (including the JS that drives the toolbar buttons).
- Remedy: `mergeIntoParams()` now **preserves the original data type** - a string stays a string (decoded, merged, re-encoded), while a Registry/array/object is mutated in place as before. An unknown, non-empty type is never overwritten - in that case the method changes nothing (returns `null` and the caller leaves the original data untouched) rather than guessing and destroying something.
- Reading the parameters for Export now also handles `$data->params` as a string correctly (previously it fell into the "unknown type → empty array" branch, which could have made Export itself empty/non-functional).
- The whole `prepareAdminForm()` is additionally wrapped in `try/catch` as a safety net - any unforeseen exception can no longer surface as a broken page; the run is silently skipped instead.
- Verified with 8 new tests covering exactly the suspected scenario (`$data->params` as a JSON string, as an empty string, and as a completely unknown type as a safety net), plus a re-run of all previous regression tests.

## 1.16.0 (2026-08-03)
- New "Custom JavaScript" field in the Advanced section, right after "Custom CSS" - the last 5% for cases CSS cannot solve.
- The JS is injected via `WebAssetManager::addInlineScript()` with `defer`, on the admin login page only (the same `isLoginPage()` guard as the CSS and the logo/footer injection).
- Same trust model as Custom CSS and Footer text (admin-only, `filter="raw"`) - no new risk category, just an extension of the existing trust in content entered by the administrator.
- Export/Import and Presets include `custom_js` automatically, with no additional logic - the mechanism is generic across all parameter keys.
- Verified with 4 tests: injection when the field is filled, no injection when empty, CSS and JS working side by side, automatic inclusion in the export.

## 1.15.1 (2026-08-03)
- FIX: the Export/Import and Preset fields were not updating in real Joomla, even though the system message reported success. Cause: `Form::setValue()` only changes a temporary copy of the field on the `$form` object, but `onContentPrepareForm` fires BEFORE Joomla binds `$data` to the form (`$form->bind($data)`) - that binding silently overwrote the `setValue()` calls with the originally stored values.
- Remedy: values are now written directly into `$data->params` (mutating the Registry/array/object in place) - exactly as described by the official Joomla documentation for this event ("If you set any of these properties then they will be modified in the form data which is presented to the user"). The new `applyValuesToData()` method replaces the former `applyValuesToForm()`.
- Verified with end-to-end tests simulating the real flow: export/import/preset values are now verifiably present in the `$data->params` object after `prepareAdminForm()` runs, not just on `$form`.

## 1.15.0 (2026-08-03)
- REBRAND: complete rename to `fgadminlogincustom` - new Joomla `element`, new folder (`plg_system_fgadminlogincustom`), new main XML file, new PHP namespace/class (`Fero\Plugin\System\FgAdminLoginCustom\Extension\FgAdminLoginCustom`), new language keys (`PLG_SYSTEM_FGADMINLOGINCUSTOM_*`) and new language file names. From Joomla's point of view this is a completely new, standalone plugin - not an update of the original `adminlogincustom` (which stays installed independently if it was previously deployed; this version neither updates nor removes it).
- `<author>` set to "Fero".
- Version numbering continues from 1.14.0 of the original plugin (it does not restart at 1.0.0). The changelog history below (up to and including v1.14.0) documents development under the original `adminlogincustom` name and is kept unchanged.
- No functional change compared to v1.14.0 - all parameters, presets, export/import and CSS logic are identical; only the extension identity changed.

## 1.14.0 (2026-08-03)
- New "Card shadow" parameter (None/Subtle/Medium/Strong) in the "Login card & colors" section, right after "Card border radius".
- The default value "None" means no change compared to previous versions (full backward compatibility).
- Fixed box-shadow presets (increasing depth): Subtle `0 1px 3px rgba(0,0,0,.15)`, Medium `0 4px 12px rgba(0,0,0,.18)`, Strong `0 12px 32px rgba(0,0,0,.28)`.
- Verified with 7 tests: backward compatibility (both a missing value and an explicit "none"), all 3 shadow levels, safe behaviour on an invalid value, and combination with the other card properties.

## 1.13.0 (2026-08-03)
- Automatic contrasting text color: when a background color is set (`card_bg`, `btn_bg`, `header_bg`) and the corresponding text color field (`card_text`, `btn_text`, `header_text`) is left empty, a readable black/white text color is now computed automatically from the WCAG relative luminance instead of leaving the text unstyled (the actual contrast ratio against both black and white is compared and the higher one wins - not just a simple brightness threshold).
- This addresses a real readability risk: previously, setting a dark custom background and forgetting to set the text color left the Atum template's default (often dark) text in place - potentially unreadable. It now switches to white automatically.
- New shared `autoContrastColor()` method.
- 100% backward compatible: an explicitly set text color always takes precedence over the automatic calculation - no behaviour change for existing configurations with colors filled in. If no background is set either, behaviour is unchanged (no CSS rule, template color).
- Verified with 13 automated tests: accuracy of the WCAG algorithm against 6 reference colors, backward compatibility, precedence of the explicit choice, and integration in all three places (card/buttons/header) as well as the full `buildCss()` run.

## 1.12.0 (2026-07-24)
- New "Theme Preset" section (first tab) with an "Apply a preset" dropdown: Default (reset to Atum template colors), Dark, Corporate Blue, Green, Orange, Minimal.
- Same two-step pattern as Import in v1.11.0: pick a preset → Save (the color fields across the form are pre-filled, confirmed by a system message) → review → Save again to store.
- Presets change ONLY color/background fields (`bg_type`, `bg_color`/`grad_*`, `card_*`, `btn_*`, `link_color`, `header_bg`/`header_text`) - they never touch layout/privacy toggles (`hide_sidebar`, `hide_chrome`, `hide_forgot`, `hide_template_logo`, `hide_generator`, `hide_lang_switcher`), so applying a preset never silently changes structural settings.
- REFACTOR: shared `applyValuesToForm()` method extracted for both import and presets - no duplicated logic.
- Deliberate scope limit (against an original proposal of 9 themes): "Glass", "Material", "Bootstrap" and "Modern" were left out - they would require new CSS properties outside the current color model (backdrop-filter blur, box-shadow elevation), not just different colors.
- Verified with 5 tests: applying a preset, resetting to empty, safe silent no-op on an unknown preset key, no action on an empty value, and layout toggles remaining untouched.

## 1.11.0 (2026-07-24)
- New "Export / Import" tab in the plugin settings.
- "Export (current settings)" - a read-only field that always shows the currently stored parameters as formatted JSON. Copying it allows the whole design to be transferred to another site.
- "Import" - paste JSON exported from another site and click Save. The fields are pre-filled with the imported values on the same screen (confirmed by a system message) - review them and click Save once more to actually store them. The field clears itself after use.
- DELIBERATE DESIGN - a two-step import instead of a one-step one: `onExtensionBeforeSave` (which would be required to apply values immediately on the first Save) has a documented history of not firing specifically for plugin saves via com_plugins (Joomla core issue #7529; fix #41175 targeted at [6.1], not certain whether it is part of the current minor release). Rather than building on that risk, the reliable `onContentPrepareForm` is used (no such history), which fires on every reload of the edit form - which is exactly what the "Save" button (without Close) naturally does.
- Invalid JSON in the Import field is safely ignored with a warning system message; the field is cleared regardless.
- Verified with 5 automated tests: the export contains the right data and excludes meta keys, isolation from other plugins/forms, correct application of the import including clearing the field, and safe failure on invalid JSON.

## 1.10.0 (2026-07-24)
- New parameters in the "Logo & Layout" section: "Logo fixed height (px)" and "Fit mode" (Contain/Cover).
- The default behaviour (height field empty) is unchanged - the logo scales freely according to `Logo max width`, the aspect ratio is always preserved, no distortion. This is 100% backward compatible with previous versions.
- If "Logo fixed height" is filled in, the image gets a fixed `width × height` box and `object-fit` decides how the logo fits into it (contain = the whole logo stays visible, empty space may remain; cover = the box is filled completely, the logo may be cropped). Particularly useful across multiple sites with logos of different aspect ratios, where a consistent box size is needed.
- An invalid/missing `logo_fit` value safely falls back to `contain`.
- Verified with tests: backward compatibility (output without a height set is bit-identical to v1.9.2) and the new branch with a fixed box and both fit modes.

## 1.9.2 (2026-07-24)
- ROBUSTNESS FIX: the fragile non-greedy regex (`.*?</div>`) used to insert the logo into the `.main-brand.logo` slot was replaced by the new `replaceBalancedDivContent()` method - a depth-counting scanner that correctly finds the matching closing `</div>` even if a future version of the Atum template nests further divs inside that slot (where the original regex would stop at the first, wrong `</div>` and break the HTML).
- A full `DOMDocument`/`DOMXPath` approach over the whole `$body` was considered but deliberately NOT used - re-serialising the entire page would risk collateral damage to inline JSON in the debug bar and to the SVG passkey icon, which is a bigger risk than the original problem. The balanced-tag scanner solves exactly the same problem (fragility with nested divs) without that side risk, since it only changes a precisely bounded section of the string.
- Verified with tests: real production HTML output (logo, footer, sidebar, balanced tags) and a synthetic scenario with a nested div inside the logo slot - correct result in both cases.

## 1.9.1 (2026-07-24)
- REFACTOR: `buildCss()` (previously ~230 lines in a single method) was split into 9 small, single-purpose methods: `buildBackgroundCss()`, `buildChromeCss()`, `buildSidebarCss()`, `buildHeaderBarCss()`, `buildLoginCardCss()`, `buildButtonCss()`, `buildLogoCss()`, `buildFooterCss()`, `buildCustomCss()`, plus the helper `resolveButtonColors()` (button colors are computed once and shared with the header bar section for `header_match_btn`).
- `buildCss()` now merely assembles the results of these methods via `array_merge` and `implode`.
- No functional change: verified by an automated comparison of the output of the old (monolithic) and new (split) implementation across 5 different parameter combinations (defaults, all colors set, gradient, background image, no colors) - the resulting CSS is identical.

## 1.9.0 (2026-07-24)
- The "Custom logo" and "Background image" fields were switched back to `type="media"` (the standard Joomla Media Manager browse dialog), as in v1.5.0. All other features added since v1.6.0 (Header bar, button color matching, etc.) are retained.
- Note: if the Media Manager behaves unreliably on a given server/browser, the field value can still be typed manually into the text input next to the image preview - the Joomla `media` field allows this regardless of the state of the Media Manager dialog.

## 1.8.1 (2026-07-24)
- The "Sign in with a passkey" button (`.btn-secondary`) now inherits the same colors (background/hover/text) as the login button (`.btn-primary`) through the existing Button background/hover/text parameters - no new parameter, just an extension of the existing selectors.

## 1.8.0 (2026-07-24)
- New "Match frontend-link button to login button" parameter in the "Header bar" section: the frontend link button in the header bar takes the same background/hover/text colors as the login button (the Button background/hover/text parameters from the "Login card & colors" section) - no duplicated color, just a toggle.
- The selector deliberately targets only `a.header-item-content` (the link itself), so it does not affect the language switcher, which shares the `.header-item-content` class but sits on a `<div>`, not an `<a>`.

## 1.7.0 (2026-07-24)
- New "Header bar" tab: background color (`header_bg`), text/link/icon color (`header_text`) and a "Hide language switcher" toggle (`hide_lang_switcher`) for the header bar (`#header`) on the login page.
- Header bar styling is independent of "Hide header & footer" - if the header is hidden, these rules simply have nothing to style.

## 1.6.0 (2026-07-24)
- CHANGE: the "Custom logo" and "Background image" fields were switched from `type="media"` to `type="text"` (a plain text field where the relative path is entered manually, e.g. `images/logo.png`). Reason: on some hosting setups (typically jailed shared hosting with a restricted `open_basedir` or a symlinked images folder) the native Joomla Media Manager (com_media) is non-functional - it does not list files and media value validation fails on save regardless of the file extension. A text field bypasses this problem entirely, since it does not go through com_media validation.
- Field descriptions updated with instructions on the path format (relative to the site root, no leading slash).
- `cleanMediaUrl()` is unchanged - it works equally well for manually entered relative and absolute URLs.

## 1.5.0 (2026-07-18)
- New "Footer text below the form" parameter: optional text/HTML (e.g. "Created by ...") inserted server-side right after the closing form tag - exactly where the "Forgot your login details?" link used to be. HTML including links is supported (`filter="raw"`, admin-only setting).
- The footer has its own `.alc-footer` class (deliberately without `.text-center`, so the `hide_forgot` rule cannot hide it) with restrained styling: centered, smaller type, opacity 0.85.
- Safe escaping of `$` and `\` in the replacement string (no backreference side effects from admin-entered HTML).
- Refactor: `replaceLogo()` renamed to `modifyBody()`, handling both the logo and the footer in a single `onAfterRender` pass.

## 1.4.2 (2026-07-18)
- Background image: removed `background-attachment: fixed` (it causes broken/zoomed rendering on iOS Safari, and the login page does not scroll anyway). The background stays `center center / cover no-repeat` - centered and scaled to the screen size.
- Added `min-height: 100vh` to the body when a custom background is set, so it always covers the whole viewport.

## 1.4.1 (2026-07-18)
- FIX: the `.login > .text-center` selector from v1.4.0 also hid the logo slot (`main-brand logo text-center` is a direct child of `.login` as well). Replaced with `.login form ~ .text-center` - only the password recovery block AFTER the form is hidden; the logo, which precedes the form, cannot be affected.

## 1.4.0 (2026-07-18)
- New "Hide 'Forgot your login details?' link" parameter (enabled by default): hides the `.login > .text-center` block containing the password recovery link below the form, which points to guide.joomla.org and reveals the CMS.

## 1.3.0 (2026-07-18)
- CHANGE: the logo is no longer inserted with JavaScript but server-side in `onAfterRender` - the content of the native `.main-brand.logo` slot is replaced with the custom logo directly in the HTML before it is sent to the browser. This removes the flicker (large template logo → shrinking after `DOMContentLoaded`): the correct image at the correct size is there from the first paint, with no layout shift.
- The regex deliberately matches only a div carrying both the `main-brand` and `logo` classes (the slot above the form); the sidebar `#main-brand` without the `logo` class is left untouched. Fallback: if the slot does not exist, the logo is inserted as the first element of the `.login` box. Verified against real HTML from Joomla 6.1.2 / Atum.
- Removed the JS injection (`buildLogoScript`); CSS sizing applies to `.alc-logo-img` and `.main-brand img` immediately.
- Refactor: the login page conditions were consolidated into `isLoginPage()`.

## 1.2.1 (2026-07-18)
- The logo is centered under all circumstances: the `.alc-logo` container (and the native `.login .main-brand` slot) is switched to flex with `justify-content: center`, `width: 100%`, `margin: 0 auto` and `float: none` (all with `!important`). The image uses `display: block`, `margin: 0 auto`, `width/height: auto` with a max-width from the parameter - no stretching and no upscaling of small logos.

## 1.2.0 (2026-07-18)
- FIX: the custom logo was not displayed - the Atum login page has no `.card` (the white box is a `div` with the `login` class). The JS now inserts the logo directly into the native Atum `.login .main-brand` slot above the form (with a fallback to `.login` / `.card`).
- FIX: the `hide_template_logo` and `hide_sidebar` selectors were too generic (`.view-login .logo`, `.main-brand`) and also hid the native login logo slot above the form. Narrowed down: the header logo only via `#header .logo`, the sidebar only via `#sidebar-wrapper` (markup verified against joomla-cms master: `atum/login.php`, `mod_login/tmpl/default.php`).
- Card styling (background/text/radius) now also applies to the `.login` box, not just `.card`.

## 1.1.0 (2026-07-18)
- New "Hide left sidebar" parameter (enabled by default): hides the entire left sidebar of the login page - both the template logo panel and the "Need Support?" module with Joomla links; the content expands to full width.
- New "Remove generator meta tag" parameter (enabled by default): removes the meta generator tag that reveals Joomla in the login page source.

## 1.0.0 (2026-07-18)
- First release (Joomla 6 native: PSR-4, `SubscriberInterface`, DI via `services/provider.php`; also compatible with J4/J5).
- Custom logo above the login card (media field, configurable max width), optional hiding of the template logo and the Joomla icon (`.login-joomla`).
- Background: solid color / linear gradient (with angle) / image with a color overlay and configurable opacity.
- Login card styling: background, text color, border radius; button colors (background/hover/text) and link color.
- Optional hiding of the header and footer (full-screen login).
- Custom CSS field (applied last, on the login page only).
- Injection via the WebAssetManager in `onBeforeCompileHead`, for guest users only and with the option empty or `com_login`.
