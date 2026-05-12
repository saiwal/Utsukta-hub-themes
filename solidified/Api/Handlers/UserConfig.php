<?php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

/**
 * GET /api/userconfig
 *
 * Returns viewer identity, pconfig, and app lists (pinned / featured / system).
 * Safe for all viewer types: local, remote, anonymous.
 */
class UserConfig
{
    public function get(): void
    {

        $viewer  = self::buildViewer();
        $pconfig = $viewer['is_local'] ? self::loadPconfig($viewer['uid']) : [];
        $apps    = self::buildAppLists($viewer['is_local'], $viewer['uid']);

        Response::send(array_merge(
            ['viewer' => $viewer],
            $pconfig ? ['pconfig' => $pconfig] : [],
            $apps,
        ));
    }

    // ── Viewer identity ───────────────────────────────────────────────────────

    private static function buildViewer(): array
    {
        $channel  = \App::get_channel();
        $observer = \App::get_observer();
        $is_local = (bool) local_channel();
        $ob_hash  = $observer['xchan_hash'] ?? '';

        return [
            'is_local'  => $is_local,
            'is_remote' => !$is_local && $ob_hash !== '',
            'is_anon'   => !$is_local && $ob_hash === '',
            'is_admin'  => $is_local && is_site_admin(),
            'uid'       => $is_local ? (int) local_channel() : 0,
            'nick'      => $channel['channel_address'] ?? '',
            'name'      => $observer['xchan_name']    ?? '',
            'avatar'    => $observer['xchan_photo_m'] ?? '',
            'url'       => $observer['xchan_url']     ?? '',
            'baseurl'   => \z_root(),
        ];
    }

    // ── Per-channel config ────────────────────────────────────────────────────

    private static function loadPconfig(int $uid): array
    {
        $rows = q('SELECT cat, k, v FROM pconfig WHERE uid = %d', $uid);
        $out  = [];
        foreach (($rows ?: []) as $row) {
            $out[$row['cat']][$row['k']] = $row['v'];
        }
        return $out;
    }

    // ── App lists ─────────────────────────────────────────────────────────────

    private static function buildAppLists(bool $is_owner, int $uid): array
    {
        $is_owner
            ? self::ensureSystemAppsImported($uid)
            : null;

        $pinned   = $is_owner ? self::ownerPinned($uid)   : self::publicPinned();
        $featured = $is_owner ? self::ownerFeatured($uid) : self::publicFeatured();
        $system   = self::allSystemApps();

        if (!$is_owner) {
            $pinned   = self::filterOwnerOnly($pinned);
            $featured = self::filterOwnerOnly($featured);
        }

        usort($featured, 'Zotlabs\Lib\Apps::app_name_compare');
        $featured = \Zotlabs\Lib\Apps::app_order($uid, $featured, 'nav_featured_app');

        return [
            'pinned'   => $pinned,
            'featured' => $featured,
            'system'   => $system,
        ];
    }

    // ── Owner-specific app helpers ────────────────────────────────────────────

    private static function ensureSystemAppsImported(int $uid): void
    {
        if (get_pconfig($uid, 'system', 'import_system_apps') !== datetime_convert('UTC', 'UTC', 'now', 'Y-m-d')) {
            \Zotlabs\Lib\Apps::import_system_apps();
            set_pconfig($uid, 'system', 'import_system_apps', datetime_convert('UTC', 'UTC', 'now', 'Y-m-d'));
        }
        if (get_pconfig($uid, 'system', 'force_import_system_apps') !== STD_VERSION) {
            \Zotlabs\Lib\Apps::import_system_apps();
            set_pconfig($uid, 'system', 'force_import_system_apps', STD_VERSION);
        }
    }

    private static function ownerPinned(int $uid): array
    {
        $list = \Zotlabs\Lib\Apps::app_list($uid, false, ['nav_pinned_app']) ?: [];
        $apps = array_map('\Zotlabs\Lib\Apps::app_encode', $list);
        \Zotlabs\Lib\Apps::translate_system_apps($apps);
        usort($apps, 'Zotlabs\Lib\Apps::app_name_compare');
        return \Zotlabs\Lib\Apps::app_order($uid, $apps, 'nav_pinned_app');
    }

    private static function ownerFeatured(int $uid): array
    {
        $list = \Zotlabs\Lib\Apps::app_list($uid, false, ['nav_featured_app']) ?: [];
        $apps = array_map('\Zotlabs\Lib\Apps::app_encode', $list);
        \Zotlabs\Lib\Apps::translate_system_apps($apps);
        return $apps;
    }

    // ── Public (non-owner) fallbacks ──────────────────────────────────────────

    private static function publicPinned(): array
    {
        // Expose a minimal curated set so the sidebar is never empty
        $public_names = ['Directory', 'Help'];
        if (can_view_public_stream()) {
            $public_names[] = 'Network';
        }

        $system = self::allSystemApps();

        $pinned = array_values(array_filter(
            $system,
            fn($app) => in_array($app['name'] ?? '', $public_names, true),
        ));

        usort($pinned, function ($a, $b) use ($public_names) {
            $ia = array_search($a['name'] ?? '', $public_names);
            $ib = array_search($b['name'] ?? '', $public_names);
            return $ia - $ib;
        });

        return $pinned;
    }

    private static function publicFeatured(): array
    {
        return self::allSystemApps();
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    private static function allSystemApps(): array
    {
        $apps = \Zotlabs\Lib\Apps::get_system_apps(true);
        \Zotlabs\Lib\Apps::translate_system_apps($apps);
        usort($apps, 'Zotlabs\Lib\Apps::app_name_compare');
        return $apps;
    }

    private static function filterOwnerOnly(array $list): array
    {
        return array_values(array_filter(
            $list,
            fn($app) => empty($app['requires'])
                || strpos($app['requires'], 'local_channel') === false,
        ));
    }
}
