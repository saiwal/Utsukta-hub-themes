<?php
// Api/Concerns/FiltersBlockedChannels.php
namespace Utsukta\SpaCore\Api\Concerns;

use Zotlabs\Lib\Apps;

// Personal (per-viewer) block list — the superblock addon's, read and written
// in its own storage, and gated the way the addon gates itself: nothing applies
// unless the addon is enabled on the hub *and* the channel installed its app.
//
// Two halves, split by where the work can happen. The addon's core hooks
// (item_store_before, perm_is_allowed, post_mail, enotify_store) drop blocked
// content on arrival; the SPA relies on them rather than registering copies.
// But the SPA queries `item`/`notify` directly and never runs the stream_item /
// enotify_format display hooks, so the read-side filtering lives here.
trait FiltersBlockedChannels
{
    // Several callers per request ask (Item's comment modes, Network's filters).
    private array $superblockActiveMemo = [];

    protected function superblockActive(int $uid): bool
    {
        if (!$uid) return false;

        // plugin_is_installed(), not App::$plugins — core never fills that.
        return $this->superblockActiveMemo[$uid] ??= plugin_is_installed('superblock')
            && Apps::addon_app_installed($uid, 'superblock');
    }

    protected function superblockSettings(int $uid): array
    {
        // Defaults are the addon's (SuperblockSettings): both on.
        $s = get_pconfig($uid, 'superblock', 'settings');
        return array_merge(['block_reshares' => true, 'block_incoming' => true], is_array($s) ? $s : []);
    }

    // The list as superblock stores it. Its 2025 rewrite (ChannelBlockList)
    // saves an array of ['hash' => …, 'until' => ISO8601?]; hubs that haven't
    // re-saved since still hold the old comma-separated string of hashes, and
    // the addon reads both. Every SPA stream endpoint calls this, so an
    // explode() on the array form is a TypeError that 500s all of them.
    protected function blockedEntries(int $uid): array
    {
        $data = get_pconfig($uid, 'system', 'blocked');

        if (is_array($data)) {
            return array_values(array_filter($data,
                fn($e) => is_array($e) && trim((string)($e['hash'] ?? '')) !== ''));
        }

        return array_map(fn($h) => ['hash' => $h],
            array_values(array_filter(array_map('trim', explode(',', (string) $data)))));
    }

    // A timed block stops applying once `until` passes (ChannelBlock::validate()).
    protected static function blockLive(array $e, int $now): bool
    {
        if (empty($e['until'])) return true;
        $t = strtotime($e['until']);
        return $t === false || $t > $now;
    }

    protected function blockedXchans(int $uid): array
    {
        if (!$this->superblockActive($uid)) return [];

        $now = time();
        return array_values(array_map(
            fn($e) => trim($e['hash']),
            array_filter($this->blockedEntries($uid), fn($e) => self::blockLive($e, $now))
        ));
    }

    protected function blockedSqlClause(string $col, array $blocked): string
    {
        if (!$blocked) return '';

        $list = implode(',', array_map(fn($h) => "'" . dbesc($h) . "'", $blocked));
        return " AND $col NOT IN ($list) ";
    }

    // Superblock::filterItem()'s reshare rule: with block_reshares on, a post
    // whose body embeds a [share] of a blocked channel is blocked too. The
    // share's profile='…' attribute is matched against the blocked hashes and
    // their xchan URLs (filterByProfileUrl()).
    //
    // ponytail: LIKE over body, short-circuited by the '[share' test so
    // share-free rows pay one scan; LIKE wildcards in a URL (_ %) only ever
    // over-match. Upgrade path is a share-target index, if blocklists get long.
    protected function blockedShareSqlClause(string $col, array $blocked, int $uid): string
    {
        if (!$blocked || !$this->superblockSettings($uid)['block_reshares']) return '';

        $in   = implode(',', array_map(fn($h) => "'" . dbesc($h) . "'", $blocked));
        $urls = array_column(q("SELECT xchan_url FROM xchan WHERE xchan_hash IN ($in)") ?: [], 'xchan_url');

        $terms = array_map(
            fn($v) => "$col NOT LIKE '%" . dbesc("profile='$v'") . "%'",
            array_unique(array_filter(array_merge($blocked, $urls)))
        );

        return " AND ($col NOT LIKE '%[share%' OR (" . implode(' AND ', $terms) . ")) ";
    }

    // The same reshare rule for one already-fetched body (Display's root).
    protected function bodySharesBlocked(string $body, array $blocked, int $uid): bool
    {
        if (!$blocked || !$this->superblockSettings($uid)['block_reshares']) return false;
        if (!preg_match_all("/\[share\s+[^]]*?profile='([^']*)'/s", $body, $m)) return false;

        foreach (array_unique($m[1]) as $profile) {
            if (in_array($profile, $blocked, true)) return true;
            $r = q("SELECT xchan_hash FROM xchan WHERE xchan_url = '%s'", dbesc($profile));
            foreach ($r ?: [] as $x) {
                if (in_array($x['xchan_hash'], $blocked, true)) return true;
            }
        }
        return false;
    }

    // Author, owner and reshare clauses together — what a stream query wants.
    protected function blockedItemSql(int $uid, array $blocked, string $alias = 'item'): string
    {
        return $this->blockedSqlClause("$alias.author_xchan", $blocked)
            . $this->blockedSqlClause("$alias.owner_xchan", $blocked)
            . $this->blockedShareSqlClause("$alias.body", $blocked, $uid);
    }

    protected function isBlockedHash(array $blocked, ?string $hash): bool
    {
        return $hash !== null && $hash !== '' && in_array($hash, $blocked, true);
    }
}
