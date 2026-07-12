<?php
// Api/Concerns/FiltersBlockedChannels.php
namespace Theme\Solidified\Api\Concerns;

// Personal (per-viewer) block list. Storage is compatible with classic Hubzilla's
// superblock addon (pconfig system/blocked, comma-separated xchan hashes), but
// filtering here is native to the SPA's own read endpoints — the SPA queries the
// item table directly and never goes through the addon's hook pipeline.
trait FiltersBlockedChannels
{
    protected function blockedXchans(int $uid): array
    {
        if (!$uid) return [];

        $data = get_pconfig($uid, 'system', 'blocked');
        if (!$data) return [];

        return array_values(array_filter(array_map('trim', explode(',', $data))));
    }

    protected function blockedSqlClause(string $col, array $blocked): string
    {
        if (!$blocked) return '';

        $list = implode(',', array_map(fn($h) => "'" . dbesc($h) . "'", $blocked));
        return " AND $col NOT IN ($list) ";
    }

    protected function isBlockedHash(array $blocked, ?string $hash): bool
    {
        return $hash !== null && $hash !== '' && in_array($hash, $blocked, true);
    }
}
