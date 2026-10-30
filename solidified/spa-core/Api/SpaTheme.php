<?php
// Api/SpaTheme.php
namespace Utsukta\SpaCore\Api;

use Zotlabs\Extend\Route;
use Zotlabs\Lib\Config;

// Exactly one enabled spa-core theme owns the /spa route and the shared hooks.
// Core's Router include_once()s *every* route matching 'spa', and each theme's
// mod/spa.php declares the same Zotlabs\Module\Spa class, so two registered
// routes is a fatal on every /spa request; two hook sets would double every
// push/chatfed/forum notification. The owner is whoever holds the route; on
// its disable, ownership passes to the next enabled theme that ships mod/spa.php.
// Hook functions follow the build's slug rewrite: <theme>_<fn> in hooks/<file>.php.
final class SpaTheme
{
    // [hook, hooks/<file>.php, <theme>_<fn>]
    private const HOOKS = [
        ['enotify_store_end', 'webpush', 'webpush_send'],
        ['enotify_store_end', 'notifyrace', 'notify_race_cleanup'],
        ['spa_webpush', 'webpush', 'webpush_send'],
        ['chat_post', 'chatfed', 'chatfed_chat_post'],
        ['daemon_addon', 'chatfed', 'chatfed_daemon'],
        ['post_remote', 'quotes', 'quote_post_remote'],
        ['post_remote_update', 'quotes', 'quote_post_remote'],
        ['item_stored', 'forumnotify', 'forum_item_stored'],
    ];

    public static function owner(): ?string
    {
        foreach (Route::get() as $r) {
            if (is_array($r) && ($r[1] ?? '') === 'spa'
                && preg_match('#^view/theme/([^/]+)/mod/spa\.php$#', $r[0], $m))
                return $m[1];
        }
        return null;
    }

    public static function enable(string $me): void
    {
        $o = self::owner();
        // Owner removed from disk without being disabled: its route is dead.
        if ($o !== null && !is_file("view/theme/$o/mod/spa.php")) {
            self::set($o, false);
            $o = null;
        }
        // Re-enabling the owner re-registers too, picking up hooks added since.
        if ($o === null || $o === $me) self::set($me, true);
    }

    public static function disable(string $me): void
    {
        if (self::owner() !== $me) return;
        self::set($me, false);
        // Admin/Themes saves allowed_themes *after* this runs, so it still lists $me.
        foreach (explode(',', (string) Config::Get('system', 'allowed_themes', '')) as $t) {
            $t = trim($t);
            if ($t !== '' && $t !== $me && is_file("view/theme/$t/mod/spa.php")) {
                self::set($t, true);
                return;
            }
        }
    }

    private static function set(string $t, bool $on): void
    {
        $on ? Route::register("view/theme/$t/mod/spa.php", 'spa')
            : Route::unregister("view/theme/$t/mod/spa.php", 'spa');
        foreach (self::HOOKS as [$hook, $file, $fn]) {
            $on ? register_hook($hook, "view/theme/$t/hooks/$file.php", "{$t}_$fn")
                : unregister_hook($hook, "view/theme/$t/hooks/$file.php", "{$t}_$fn");
        }
    }
}
