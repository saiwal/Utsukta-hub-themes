<?php
// Api/HubDefaults.php
namespace Utsukta\SpaCore\Api;

use Zotlabs\Lib\Config;

// Hub-wide display defaults, set by the admin at /admin/themes/<theme> (the
// theme's php/config.php theme_admin()). Stored in site Config cat 'spa' under
// the same key as the per-user pconfig, so every Utsukta theme sharing
// spa-core reads one set. They are defaults only: a user's own pconfig wins.
final class HubDefaults
{
    public const FONT_SIZES    = ['small', 'medium', 'large', 'xl'];
    public const FONT_FAMILIES = [
        'system', 'serif', 'monospace', 'nunito', 'saira', 'share-tech',
        'playfair', 'libre-baskerville', 'comfortaa', 'space-mono', 'iosevka',
        'righteous', 'playwrite-england', 'comic', 'opendyslexic',
        'inter', 'atkinson-hyperlegible', 'literata', 'jetbrains-mono',
    ];
    public const RADII = ['none', 'sm', 'default', 'lg', 'xl'];

    /** key => allowed values (null = free-form, checked in clean()). First value is the built-in default. */
    public const CHOICES = [
        'color_scheme'  => null,
        'font_size'     => self::FONT_SIZES,
        'font_family'   => self::FONT_FAMILIES,
        'corner_radius' => self::RADII,
        'bg_url'        => null,
        'bg_fit'        => ['cover', 'tile'],
        'scroll_style'  => ['endless', 'load_more'],
        'post_height'   => null,
        'comment_order' => ['oldest_first', 'newest_first'],
        'composer_mode' => ['modal', 'dock', 'page'],
        'post_mode'     => ['modal', 'dock', 'page'],
        'thread_mode'   => ['threaded', 'flat'],
    ];

    public const BUILTIN = [
        'color_scheme' => 'light', 'font_size' => 'medium', 'font_family' => 'system',
        'corner_radius' => 'default', 'bg_url' => '', 'bg_fit' => 'cover',
        'scroll_style' => 'endless', 'post_height' => '310', 'comment_order' => 'oldest_first',
        'composer_mode' => 'modal', 'post_mode' => 'modal', 'thread_mode' => 'threaded',
    ];

    /** Validated value, or null when $v isn't acceptable for $k ('' = unset). */
    public static function clean(string $k, $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || !array_key_exists($k, self::CHOICES)) return null;
        switch ($k) {
            // 'custom' needs a colour set the admin form doesn't collect
            case 'color_scheme':
                return ($v !== 'custom' && ColorSchemes::valid($v)) ? $v : null;
            case 'bg_url':
                return ((filter_var($v, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $v)) || preg_match('#^/[^/]#', $v))
                    ? notags($v) : null;
            case 'post_height':
                if (!is_numeric($v)) return null;
                $n = intval($v);
                return (string) ($n > 0 ? min(max($n, 100), 5000) : 0);
            default:
                return in_array($v, self::CHOICES[$k], true) ? $v : null;
        }
    }

    /** Only the keys the admin actually set. */
    public static function get(): array
    {
        $out = [];
        foreach (array_keys(self::CHOICES) as $k) {
            $v = self::clean($k, Config::Get('spa', $k, ''));
            if ($v !== null) $out[$k] = $v;
        }
        return $out;
    }

    /** A user's own pref for $k when set and valid, else the hub default, else the built-in. */
    public static function pref(int $uid, string $k): string
    {
        $v = get_pconfig($uid, 'spa', $k, null);
        return ($v === null ? null : self::clean($k, $v)) ?? self::value($k);
    }

    /** Hub default for $k, falling back to the built-in one. */
    public static function value(string $k): string
    {
        return self::clean($k, Config::Get('spa', $k, '')) ?? self::BUILTIN[$k];
    }
}
