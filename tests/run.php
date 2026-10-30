<?php

/**
 * Plain-PHP tests (no PHPUnit needed): php tests/run.php
 */

declare(strict_types=1);

define('_JEXEC', 1);

require __DIR__ . '/registry_stub.php';

foreach (['ColorHelper', 'MediaUrl', 'SettingsSanitizer', 'ImportExport', 'BodyModifier', 'ThemePresets', 'CssBuilder'] as $h) {
    require __DIR__ . '/../src/Helper/' . $h . '.php';
}

use FG\Plugin\System\AdminLoginCustom\Helper\BodyModifier as BM;
use FG\Plugin\System\AdminLoginCustom\Helper\ColorHelper as C;
use FG\Plugin\System\AdminLoginCustom\Helper\CssBuilder as B;
use FG\Plugin\System\AdminLoginCustom\Helper\ImportExport as IE;
use FG\Plugin\System\AdminLoginCustom\Helper\MediaUrl as M;
use FG\Plugin\System\AdminLoginCustom\Helper\SettingsSanitizer as S;
use FG\Plugin\System\AdminLoginCustom\Helper\ThemePresets as P;
use Joomla\Registry\Registry;

$fail = 0;
$n    = 0;
$eq   = static function ($actual, $expected, string $name) use (&$fail, &$n): void {
    $n++;

    if ($actual !== $expected) {
        $fail++;
        echo "FAIL $name: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
};

// ColorHelper
$eq(C::clean(' #ABC '), '#abc', 'clean short');
$eq(C::clean('#8B5A2B'), '#8b5a2b', 'clean lower');
$eq(C::clean('#12345'), '', 'clean bad length');
$eq(C::clean('red'), '', 'clean named');
$eq(C::toRgb('#fff'), [255, 255, 255], 'rgb short');
$eq(C::contrastText('#000000'), '#ffffff', 'text on black');
$eq(C::contrastText('#ffffff'), '#000000', 'text on white');
$eq(round(C::contrast('#000000', '#ffffff'), 2), 21.0, 'contrast max');
$eq(C::shade('#808080', -1.0), '#000000', 'shade black');
$eq(C::shade('#808080', 1.0), '#ffffff', 'shade white');
$eq(C::toRgba('#ff0000', 50), 'rgba(255, 0, 0, 0.50)', 'rgba');

// MediaUrl
$eq(M::clean(''), '', 'media empty');
$eq(M::clean('images/a.png#joomlaImage://local-images/a.png?width=1'), '/site/images/a.png', 'media fragment stripped');
$eq(M::clean('#joomlaImage://x'), '', 'media only fragment');
$eq(M::clean('https://e.com/a.png'), 'https://e.com/a.png', 'media absolute');
$eq(M::clean('//cdn.e.com/a.png'), '//cdn.e.com/a.png', 'media protocol relative');

// SettingsSanitizer
$r = S::sanitize([
    'brand_color' => '#8B5A2B', 'header_bg' => 'url(x)', 'bg_color' => '',
    'ctrl_radius' => '12', 'card_radius' => '99', 'logo_width' => '300', 'grad_angle' => '361',
    'btn_shadow' => 'medium', 'card_shadow' => 'huge', 'bg_type' => 'gradient', 'logo_fit' => 'stretch',
    'hide_sidebar' => '1', 'hide_forgot' => '2',
    'logo' => 'images/a.png#joomlaImage://x', 'bg_image' => '"><script>',
    'footer_text' => '<p>Made by <a href="https://x.sk">FG</a></p>',
    'custom_css' => '.login { outline: 1px solid red }',
    'custom_js' => 'alert(1)', 'theme_preset' => 'dark', 'unknown' => 'x', 'export_json' => '{}',
]);
$eq($r['values']['brand_color'], '#8b5a2b', 'san color');
$eq($r['values']['bg_color'], '', 'san empty color');
$eq($r['values']['ctrl_radius'], 12, 'san radius');
$eq($r['values']['logo_width'], 300, 'san width');
$eq($r['values']['btn_shadow'], 'medium', 'san shadow');
$eq($r['values']['bg_type'], 'gradient', 'san list');
$eq($r['values']['hide_sidebar'], 1, 'san flag');
$eq($r['values']['logo'], 'images/a.png#joomlaImage://x', 'san logo');
$eq($r['values']['footer_text'], '<p>Made by <a href="https://x.sk">FG</a></p>', 'san footer');
$eq($r['values']['custom_css'], '.login { outline: 1px solid red }', 'san css');
$eq(array_key_exists('custom_js', $r['values']), false, 'san custom_js never importable');
$eq(array_key_exists('theme_preset', $r['values']) || array_key_exists('unknown', $r['values']) || array_key_exists('export_json', $r['values']), false, 'san ignores non-importable');
$eq($r['rejected'], ['header_bg', 'card_radius', 'grad_angle', 'card_shadow', 'logo_fit', 'hide_forgot', 'bg_image'], 'san rejected');
$eq(S::sanitize(['footer_text' => '<script>alert(1)</script>'])['rejected'], ['footer_text'], 'san footer script');
$eq(S::sanitize(['footer_text' => '<img src=x onerror=alert(1)>'])['rejected'], ['footer_text'], 'san footer handler');
$eq(S::sanitize(['footer_text' => '<a href="javascript:alert(1)">x</a>'])['rejected'], ['footer_text'], 'san footer js url');
$eq(S::sanitize(['custom_css' => 'a{}</style><script>x</script>'])['rejected'], ['custom_css'], 'san css breakout');
$eq(S::sanitize(['logo' => 'javascript:alert(1)'])['rejected'], ['logo'], 'san js scheme');
$eq(S::sanitize(['ctrl_radius' => '-3'])['rejected'], ['ctrl_radius'], 'san negative');
$eq(S::sanitize(['brand_color' => ['x']])['rejected'], ['brand_color'], 'san array');

// Every theme preset passes the sanitizer untouched (presets are written by the plugin itself)
foreach (P::all() as $name => $preset) {
    $res = S::sanitize($preset);
    $eq($res['rejected'], [], "preset $name valid");
}

// ImportExport
$eq(IE::paramsToArray('{"a":1}'), ['a' => 1], 'params json');
$eq(IE::paramsToArray('garbage'), [], 'params bad json');
$eq(IE::paramsToArray(new Registry(['a' => 2])), ['a' => 2], 'params registry');
$eq(IE::paramsToArray(null), [], 'params null');
$data = ['params' => '{"keep":1}'];
IE::applyValuesToData($data, ['brand_color' => '#fff']);
$eq(json_decode($data['params'], true), ['keep' => 1, 'brand_color' => '#fff'], 'apply keeps json string shape');
$data = (object) ['params' => new Registry(['keep' => 1])];
IE::applyValuesToData($data, ['x' => 5]);
$eq($data->params instanceof Registry && $data->params->get('x') === 5 && $data->params->get('keep') === 1, true, 'apply keeps Registry shape');
$data = ['params' => ['keep' => 1]];
IE::applyValuesToData($data, ['x' => 5]);
$eq($data['params'], ['keep' => 1, 'x' => 5], 'apply keeps array shape');

// BodyModifier
$page = '<div class="login"><div class="main-brand logo text-center"><img src="atum.svg"><div class="inner">x</div></div><form></form></div>';
$out  = (string) BM::apply($page, '/a.png', '', '');
$eq(str_contains($out, 'alc-logo-img') && !str_contains($out, 'atum.svg'), true, 'body logo slot replaced');
$eq(str_contains($out, '</form></div>') && str_contains($out, '<form></form>'), true, 'body logo balanced div kept the rest');
$out = (string) BM::apply($page, '/a.png', '/d.png', '');
$eq(str_contains($out, 'alc-logo-light') && str_contains($out, 'alc-logo-dark'), true, 'body dark logo pair');
$out = (string) BM::apply($page, '', '/d.png', '');
$eq(str_contains($out, '/d.png') && !str_contains($out, 'alc-logo-dark'), true, 'body only dark logo used for all schemes');
$out = (string) BM::apply('<div class="login"><form></form></div>', '/a.png', '', '');
$eq(str_contains($out, '<div class="alc-logo">'), true, 'body logo fallback prepended');
$out = (string) BM::apply('<form></form>', '', '', 'Made by $1 \\2');
$eq(str_contains($out, '<div class="alc-footer">Made by $1 \\2</div>'), true, 'body footer literal');
$eq(BM::apply($page, '', '', ''), null, 'body nothing configured');
$eq(BM::apply('<div class="main-brand logo"><div>unbalanced', '/a.png', '', ''), null, 'body unbalanced untouched');

// CssBuilder
$eq((new B(new Registry([])))->build() !== '', true, 'css default has base rules');
$css = (new B(new Registry(['brand_color' => '#8b5a2b'])))->build();
$eq(str_contains($css, '#8b5a2b'), true, 'css brand color present');
$eq(str_contains((new B(new Registry(['ctrl_radius' => '99'])))->build(), 'border-radius: 40px'), true, 'css radius clamped');
$eq(str_contains((new B(new Registry(['custom_css' => '.x{color:red}'])))->build(), '.x{color:red}'), true, 'css custom appended');

echo $fail === 0 ? "OK: $n assertions passed\n" : "$fail of $n FAILED\n";
exit($fail === 0 ? 0 : 1);
