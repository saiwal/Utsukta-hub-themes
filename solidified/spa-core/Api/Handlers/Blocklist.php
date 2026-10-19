<?php
// Api/Handlers/Blocklist.php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\Concerns\FiltersBlockedChannels;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\Libsync;

/**
 * GET  /spa/blocklist           -> the local channel's personal block list
 * POST /spa/blocklist           -> { action: "block"|"unblock"|"siteblock", author: hash|webbie,
 *                                    duration?: { amount: 1-99, unit: minutes|hours|days|weeks|months|years } }
 * GET  /spa/blocklist/settings  -> { block_reshares, block_incoming }
 * POST /spa/blocklist/settings  -> same shape
 *
 * The superblock addon's feature, in its own storage (pconfig system/blocked,
 * superblock/settings) so the classic UI and the SPA stay interchangeable.
 * Like the addon, everything here needs the addon enabled and its app
 * installed. "siteblock" is admin-only and appends to the site-wide Config
 * system/blacklisted_channels list that Handlers/Admin.php's Security section
 * already reads/writes.
 */
class Blocklist
{
    use FiltersBlockedChannels;

    private const UNITS = ['minutes', 'hours', 'days', 'weeks', 'months', 'years'];

    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $this->requireApp($uid);

        if ((\App::$argv[2] ?? '') === 'settings') {
            Response::send($this->superblockSettings($uid));
        }

        // Every stored entry, expired ones included (the addon lists them too),
        // so a lapsed timed block can still be removed.
        $entries = $this->blockedEntries($uid);
        if (!$entries) {
            Response::send([]);
        }

        $until = array_column($entries, 'until', 'hash');
        $list  = implode(',', array_map(fn($e) => "'" . dbesc(trim($e['hash'])) . "'", $entries));
        $rows  = q("SELECT xchan_hash, xchan_name, xchan_addr, xchan_url, xchan_photo_m
                    FROM xchan WHERE xchan_hash IN ($list)");

        Response::send(array_map(fn($r) => [
            'hash'    => $r['xchan_hash'],
            'name'    => Response::decodeEntities($r['xchan_name']),
            'address' => $r['xchan_addr'],
            'url'     => $r['xchan_url'],
            'photo'   => $r['xchan_photo_m'],
            'until'   => $until[$r['xchan_hash']] ?? null,
        ], $rows ?: []));
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $this->requireApp($uid);
        $body = Auth::$parsedBody;

        if ((\App::$argv[2] ?? '') === 'settings') {
            $settings = array_merge($this->superblockSettings($uid), array_map('boolval', array_intersect_key(
                $body, ['block_reshares' => 1, 'block_incoming' => 1])));
            set_pconfig($uid, 'superblock', 'settings', $settings);
            Libsync::build_sync_packet($uid, ['config']);
            Response::send($settings);
        }

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

        $until = $action === 'block' ? $this->untilFromDuration($body['duration'] ?? null) : null;

        // Edit the raw entries, not blockedXchans(): that drops expired blocks
        // and saving it back would lose every other entry's `until`. A new
        // block replaces any earlier entry for the same channel, as
        // ChannelBlockList::add() does.
        $entries = array_values(array_filter($this->blockedEntries($uid), fn($e) => trim($e['hash']) !== $hash));
        if ($action === 'block') {
            $entries[] = $until ? ['hash' => $hash, 'until' => $until] : ['hash' => $hash];
        }

        // Keep the format we found — a superblock older than its 2025 rewrite
        // explode()s the value and would fatal on the array form — unless an
        // entry carries an expiry, which only the array form can hold.
        $asArray = is_array(get_pconfig($uid, 'system', 'blocked'))
            || array_filter($entries, fn($e) => !empty($e['until']));
        set_pconfig($uid, 'system', 'blocked', $asArray ? $entries : implode(',', array_column($entries, 'hash')));
        Libsync::build_sync_packet($uid, ['config']);

        Response::send(['status' => 'ok', 'address' => $xchan['xchan_addr'], 'until' => $until]);
    }

    private function requireApp(int $uid): void
    {
        if (!$this->superblockActive($uid)) {
            Response::error(403, 'Superblock app not installed');
        }
    }

    // Mod_Superblock::getExpirationDate(): "<amount> <unit>" from now, stored
    // as ISO8601 like ChannelBlock::toArray(). No duration = permanent.
    private function untilFromDuration($d): ?string
    {
        if (!is_array($d)) return null;

        $amount = intval($d['amount'] ?? 0);
        $unit   = strtolower((string)($d['unit'] ?? ''));
        if ($amount < 1 || $amount > 99 || !in_array($unit, self::UNITS, true)) return null;

        return (new \DateTimeImmutable("+$amount $unit", new \DateTimeZone('UTC')))
            ->format(\DateTimeInterface::ISO8601);
    }

    private function findXChanFromAuthor(string $author): array|false
    {
        $r = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($author));
        if ($r) return $r[0];

        $r = q("SELECT * FROM xchan WHERE xchan_addr = '%s' LIMIT 1", dbesc($author));
        return $r ? $r[0] : false;
    }
}
