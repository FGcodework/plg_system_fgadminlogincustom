<img src="assets/logo.png" width="120" alt="">

<h1 align="center">FG Admin Login Customizer plugin for Joomla</h1>

<p align="center">
  <img src="https://img.shields.io/github/v/release/FGcodework/plg_system_fgadminlogincustom?color=FF6B4A&label=release" alt="Latest release">
  <img src="https://img.shields.io/badge/Joomla-6.x-blue.svg?logo=joomla&logoColor=white" alt="Joomla">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-purple.svg?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/license-GPLv2%2B-green.svg" alt="License">
  <img src="https://img.shields.io/github/downloads/FGcodework/plg_system_fgadminlogincustom/total?cacheSeconds=3600" alt="Downloads">
</p>

A Joomla 6 system plugin that gives the `/administrator` login page its own
visual identity - logo, background, colors, shadows, and custom CSS/JS -
without touching the Atum template itself or the rest of the back-end.
Nothing about a logged-in admin's working experience changes; only the
login screen a guest sees before authenticating is customized.

Part of the **FG** series of Joomla extensions.

## Features

- **Custom logo** above the login form, with optional fixed width/height
  and `contain`/`cover` fit modes
- **Background**: solid color, gradient, or image (with a color+opacity
  overlay for readability)
- **Login card**: background, text color, border radius, shadow presets
- **Automatic contrast**: leave a text-color field empty and it is
  computed from the matching background color (WCAG relative luminance),
  so a dark background never silently ends up with unreadable text
- **Header bar**: background/text color, optional hiding of the language
  switcher, optional color-matching of the frontend-link button to the
  login button
- **Layout toggles**: hide the sidebar, template branding, the
  `<meta name="generator">` tag, and the native "Forgot your login
  details?" link
- **Optional footer text** below the form (HTML allowed - e.g. a
  "Created by ..." credit)
- **Six built-in color presets** (Default/Dark/Corporate Blue/Green/
  Orange/Minimal), applied in two steps (pick preset → Save → review →
  Save again) for reliability across Joomla versions
- **Export / Import** current settings as JSON, to replicate a look across
  multiple sites in a couple of clicks
- **Custom CSS and JavaScript** fields for anything the built-in options
  don't cover

## Installation

1. Download the latest release (`.zip`) from the
   [Releases](https://github.com/FGcodework/plg_system_fgadminlogincustom/releases) tab.
2. In the Joomla administrator go to **System → Install → Extensions** and
   upload the downloaded `.zip`.
3. **System → Manage → Plugins** → find "System - FG Admin Login
   Customizer" and publish it.
4. Open the plugin and configure logo, colors, and background to taste.

## Plugin settings

Settings are grouped into tabs:

| Tab | What it controls |
| --- | --- |
| **Theme Preset** | Apply one of six built-in color presets in one go (colors/background only - never touches layout or privacy toggles). |
| **Plugin** | Logo (image, width, optional fixed height + fit mode), template-logo/sidebar/chrome visibility, footer text, "Forgot your login details?" link. |
| **Background** | Background type (none/color/gradient/image), colors, gradient angle, image overlay. |
| **Login card & colors** | Card background/text/radius/shadow, button colors, link color, header-button color matching. |
| **Header bar** | Header background/text color, language-switcher visibility. |
| **Advanced** | Custom CSS and custom JavaScript, injected only on the login page (see the note below). |
| **Export / Import** | Read-only JSON export of current settings; paste JSON here + Save (twice) to import a configuration from another site. Custom JavaScript is never imported. |

Every color field left empty falls back to the Atum template's own
styling - nothing is forced unless you set it.

## A note on Custom JavaScript

The **Custom JavaScript** field runs its code on the administrator login
page - the page that carries the username and password inputs. Treat it
the way you would treat editing a template file: only ever paste code
you wrote yourself or have actually read and understood.

For that reason **Import deliberately never carries `custom_js` over**.
A settings JSON is something people pass around ("here, use my theme"),
and pasting one should not be able to execute someone else's code on
your login screen. Everything else in the export is declarative and is
imported normally; if you do want the JavaScript too, copy it across by
hand once you have reviewed it.

Note that on a stock Joomla setup only a Super User can reach the plugin
settings, and a Super User can already install extensions or edit
templates - so this field grants them nothing new. The caution above
matters when settings arrive from somewhere else, or when access to
`com_plugins` has been delegated to a lower-privileged group.

## Requirements

- Joomla 6 (5+)
- PHP 8.1+

## License

GNU General Public License v2.0 or later - see [LICENSE](LICENSE).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
