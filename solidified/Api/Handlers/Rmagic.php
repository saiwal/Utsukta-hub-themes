<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Libzot;

/**
 * Remote (reverse magic) authentication.
 *
 * POST /api/rmagic  { address, dest? }
 *
 * Resolves the visitor's home hub from their channel address and returns
 * the hub's /magic OWA handshake URL. Unlike core's /rmagic module (which
 * always lands the visitor on the site root afterwards), the caller may
 * pass `dest` — a path on this site — so the visitor returns to the page
 * they were viewing once the handshake completes.
 */
class Rmagic
{
    public function post(): void
    {
        if (\local_channel()) {
            Response::error(400, 'Already authenticated');
        }

        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($ct, 'application/json')) {
            Response::error(400, 'Content-Type must be application/json');
        }

        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $address = trim($body['address'] ?? '');
        $dest    = trim($body['dest'] ?? '');

        if (!$address || !str_contains($address, '@')) {
            Response::error(400, 'Invalid channel address');
        }

        // The remote hub redirects back to bdest after the handshake —
        // only allow paths on this site to prevent open redirects.
        if (!$dest || str_contains($dest, '://') || str_starts_with($dest, '//')) {
            $dest = '/';
        }
        $dest = \z_root() . '/' . ltrim($dest, '/');

        require_once('include/network.php');

        $r = \q("select hubloc_url, hubloc_network from hubloc where hubloc_addr = '%s' and hubloc_deleted = 0 order by hubloc_id desc",
            \dbesc($address)
        );
        if (!$r && \discover_by_webbie($address)) {
            $r = \q("select hubloc_url, hubloc_network from hubloc where hubloc_addr = '%s' and hubloc_deleted = 0 order by hubloc_id desc",
                \dbesc($address)
            );
        }

        if ($r) {
            $r   = Libzot::zot_record_preferred($r);
            $url = $r['hubloc_url'];
        } else {
            $url = 'https://' . substr($address, strpos($address, '@') + 1);
        }

        if ($url === \z_root()) {
            Response::error(400, 'This address belongs to this site — use the regular login instead');
        }

        $bdest = bin2hex(str_replace('zid=', 'zid_=', $dest));
        Response::send(['url' => $url . '/magic?owa=1&bdest=' . $bdest]);
    }
}
