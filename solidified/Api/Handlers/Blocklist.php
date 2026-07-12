<?php
// Api/Handlers/Blocklist.php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Theme\Solidified\Api\Concerns\FiltersBlockedChannels;
use Zotlabs\Lib\Config;

/**
 * GET  /api/blocklist  -> the local channel's personal block list
 * POST /api/blocklist  -> { action: "block"|"unblock"|"siteblock", author: hash|webbie }
 *
 * "block"/"unblock" manage the personal list (pconfig system/blocked, same
 * storage classic Hubzilla's superblock addon uses). "siteblock" is admin-only
 * and appends to the site-wide Config system/blacklisted_channels list that
 * Handlers/Admin.php's Security section already reads/writes.
 */
class Blocklist
{
    use FiltersBlockedChannels;

    public function get(): void
    {
        Auth::requireLocalGet();

        $uid = local_channel();
        $blocked = $this->blockedXchans($uid);

        if (!$blocked) {
            Response::send([]);
        }

        $list = implode(',', array_map(fn($h) => "'" . dbesc($h) . "'", $blocked));
        $rows = q("SELECT xchan_hash, xchan_name, xchan_addr, xchan_url, xchan_photo_m
                   FROM xchan WHERE xchan_hash IN ($list)");

        Response::send(array_map(fn($r) => [
            'hash'    => $r['xchan_hash'],
            'name'    => $r['xchan_name'],
            'address' => $r['xchan_addr'],
            'url'     => $r['xchan_url'],
            'photo'   => $r['xchan_photo_m'],
        ], $rows ?: []));
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $body = Auth::$parsedBody;

        $action = $body['action'] ?? '';
        $author = trim((string)($body['author'] ?? ''));

        if (!in_array($action, ['block', 'unblock', 'siteblock'], true)) {
            Response::error(400, 'Invalid or missing action');
        }
        if (!$author) {
            Response::error(400, 'No channel specified');
        }
        if ($action === 'siteblock' && !is_site_admin()) {
            Response::error(403, 'You do not have access to perform this operation');
        }

        $xchan = $this->findXChanFromAuthor($author);
        if (!$xchan) {
            Response::error(400, 'Invalid or unknown channel');
        }

        $hash = $xchan['xchan_hash'];

        if ($action === 'siteblock') {
            $blocked = Config::Get('system', 'blacklisted_channels', []);
            if (!is_array($blocked)) $blocked = [];
            if (!in_array($hash, $blocked, true)) {
                $blocked[] = $hash;
                sort($blocked);
                Config::Set('system', 'blacklisted_channels', $blocked);
            }
            Response::send(['status' => 'ok', 'address' => $xchan['xchan_addr']]);
        }

        $list = $this->blockedXchans($uid);

        if ($action === 'block') {
            if (!in_array($hash, $list, true)) {
                $list[] = $hash;
            }
        } else { // unblock
            $list = array_values(array_filter($list, fn($h) => $h !== $hash));
        }

        set_pconfig($uid, 'system', 'blocked', implode(',', $list));

        Response::send(['status' => 'ok', 'address' => $xchan['xchan_addr']]);
    }

    private function findXChanFromAuthor(string $author): array|false
    {
        $r = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($author));
        if ($r) return $r[0];

        $r = q("SELECT * FROM xchan WHERE xchan_addr = '%s' LIMIT 1", dbesc($author));
        return $r ? $r[0] : false;
    }
}
