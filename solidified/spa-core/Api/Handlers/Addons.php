<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;

/**
 * GET /spa/addons — SPA entry points of the enabled Hubzilla addons.
 *
 * There is no separate "SPA plugin" registry: an SPA addon is an ordinary
 * Hubzilla addon (installed/enabled through /admin/addons, which Admin.php
 * already drives) that happens to ship `addon/<name>/spa/entry.js`. The
 * enabled set is App::$plugins, so the admin toggle is the only switch.
 *
 * Unauthenticated on purpose — the list is site-global and the entry files
 * are served statically to anyone anyway; visitors run the SPA too, and an
 * addon may register visitor-visible widgets.
 */
class Addons
{
    public function get(): void
    {
        $addons = [];

        foreach (\App::$plugins as $name) {
            $name = trim($name);
            // system.addon is admin-written config, but it still reaches a
            // filesystem path here — never let it escape addon/.
            if (!preg_match('/^[a-z0-9_-]+$/i', $name)) continue;

            $file = "addon/$name/spa/entry.js";
            if (!is_file($file)) continue;

            $entry = [
                'id'  => $name,
                'url' => '/' . $file . '?v=' . self::cacheKey($file),
            ];

            // Optional stylesheet sidecar. Tailwind scans source at build time
            // and has never seen an addon, so a utility class no host file uses
            // does not exist in the host CSS. An addon that wants its own
            // styles ships them next to the entry and we <link> them in.
            $css = "addon/$name/spa/entry.css";
            if (is_file($css)) {
                $entry['css'] = '/' . $css . '?v=' . self::cacheKey($css);
            }

            $addons[] = $entry;
        }

        Response::send(['addons' => $addons]);
    }

    /**
     * Cache-buster derived from the file's mtime — deliberately not the mtime
     * itself. This endpoint is public, and a bare timestamp tells an anonymous
     * caller whether a known-vulnerable addon has been patched yet. HMAC'd
     * with the site key rather than plainly hashed, because the space of
     * plausible mtimes is small enough to hash straight through.
     */
    private static function cacheKey(string $file): string
    {
        $key = \Zotlabs\Lib\Config::Get('system', 'prvkey') ?: '';
        return substr(hash_hmac('sha256', (string) filemtime($file), $key), 0, 8);
    }
}
