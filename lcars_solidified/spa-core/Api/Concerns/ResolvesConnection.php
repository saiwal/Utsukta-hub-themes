<?php

namespace Utsukta\SpaCore\Api\Concerns;

trait ResolvesConnection
{
    // A remote identity can have multiple xchan rows sharing the same
    // xchan_url/xchan_addr but different xchan_hash (protocol change, re-key,
    // hub migration). The abook connection may reference any one of them, so
    // check them all instead of just the row a LIMIT-1 lookup happened to pick.
    // Returns [is_connected, abook_id, connected_hash] — the third element is
    // the hash the abook actually holds, which callers need when they have to
    // present one of several rows as *the* identity.
    private function connectionFor(int $localUid, array $hashes): array
    {
        $hashes = array_values(array_unique(array_filter($hashes)));
        if (!$localUid || !$hashes) {
            return [false, null, null];
        }
        $in = "'" . implode("','", array_map('dbesc', $hashes)) . "'";
        $abook = q(
            "SELECT abook_id, abook_xchan FROM abook WHERE abook_channel = %d AND abook_xchan IN ($in) LIMIT 1",
            intval($localUid)
        );
        return $abook
            ? [true, intval($abook[0]['abook_id']), $abook[0]['abook_xchan']]
            : [false, null, null];
    }
}
