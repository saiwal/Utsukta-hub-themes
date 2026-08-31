<?php

namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\Concerns\FetchesRemoteActor;
use Utsukta\SpaCore\Api\Concerns\ResolvesConnection;

class Xchan
{
    use FetchesRemoteActor;
    use ResolvesConnection;
    public function get(): void
    {
        // Batch name lookup: ?hashes=a,b,c — just enough to label ACL chips.
        // Deliberately short-circuits before the profile_load / WebFinger
        // enrichment below, which is per-identity and far too heavy to run
        // once per hash in an audience list.
        if (isset($_GET['hashes'])) {
            $this->names((string) $_GET['hashes']);
        }

        $hash = $_GET['hash'] ?? null;
        if (!$hash) Response::error(400, 'hash required');

        require_once 'include/channel.php';
        require_once 'include/permissions.php';

        // Look up all xchan rows for this identity — the same channel can have
        // more than one (protocol change, re-key, hub migration) sharing this
        // url/hash but carrying a different xchan_hash each.
        $xchans = q(
            "SELECT * FROM xchan WHERE xchan_url = '%s' OR xchan_hash = '%s'",
            dbesc($hash), dbesc($hash)
        );
        if (!$xchans) Response::error(404, 'Channel not found');

        // Connection status — only meaningful for local viewers
        [$is_connected, $abook_id, $connected_hash] = $this->connectionFor(
            local_channel(),
            array_column($xchans, 'xchan_hash')
        );

        // Pick which row represents the identity: the native zot6 row wins
        // (it carries the local channel link and magic-auth capability), then
        // whichever row this viewer's abook holds. Row order out of the query
        // is undefined, so without this a zot6 channel that is also visible
        // over ActivityPub is reported as an AP contact — which loses the
        // ?zid= on its profile link and, for a channel on this hub, the
        // channel_hash match below that yields local_nick.
        $xchan = $xchans[0];
        foreach ($xchans as $row) {
            if ($row['xchan_network'] === 'zot6') { $xchan = $row; break; }
            if ($connected_hash && $row['xchan_hash'] === $connected_hash) { $xchan = $row; }
        }

        // Enrich with full profile data if this is a local channel
        $profile_data = [];
        $local_nick = null;
        $hash_in = "'" . implode("','", array_map('dbesc', array_column($xchans, 'xchan_hash'))) . "'";
        $channel_row = q(
            "SELECT channel_address FROM channel WHERE channel_hash IN ($hash_in) LIMIT 1"
        );
        if ($channel_row) {
            $local_nick = $channel_row[0]['channel_address'];
            profile_load($local_nick);
            $p = \App::$profile ?? null;
            if ($p && !empty($p['permission_to_view'])) {
                $block = (
                    !empty($p['hidewall'])
                    && !local_channel()
                    && !remote_channel()
                );
                $location_parts = array_filter([
                    $p['locality']     ?? '',
                    $p['region']       ?? '',
                    $p['country_name'] ?? '',
                ]);
                $conn_count = q(
                    "SELECT COUNT(*) AS total FROM abook
                     WHERE abook_channel = %d AND abook_self = 0",
                    intval($p['profile_uid'])
                );
                $profile_data = [
                    'pdesc'       => $block ? '' : ($p['pdesc']    ?? ''),
                    'about'       => $block ? '' : ($p['about']    ?? ''),
                    'location'    => $block ? '' : implode(', ', $location_parts),
                    'homepage'    => $block ? '' : ($p['homepage'] ?? ''),
                    'keywords'    => $block ? [] : array_values(
                        array_filter(array_map('trim', explode(',', $p['keywords'] ?? '')))
                    ),
                    'connections' => intval($conn_count[0]['total'] ?? 0),
                    'cover'       => get_cover_photo(intval($p['profile_uid']), 'url', PHOTO_RES_COVER_1200) ?? '',
                ];
            }
        }

        // For remote channels enrich with WebFinger + AP actor data
        $actor_fields = [];
        if (!$local_nick) {
            $addr = $xchan['xchan_addr'] ?? '';
            if (str_contains($addr, '@')) {
                [, $domain] = explode('@', $addr, 2);
                if (preg_match('/^[a-zA-Z0-9.\-]+(:\d+)?$/', $domain)) {
                    $enriched = $this->fetchActorEnrichment($addr, $domain);
                    if ($enriched) {
                        $profile_data['about']        = $enriched['about'];
                        $profile_data['cover']        = $enriched['cover'];
                        $profile_data['homepage']     = $enriched['url'] ?: ($xchan['xchan_url'] ?? '');
                        $actor_fields                 = $enriched['actor_fields'];
                        $profile_data['remote_posts'] = $enriched['remote_posts'] ?? [];
                        if (!empty($enriched['photo'])) {
                            $xchan['xchan_photo_l'] = $enriched['photo'];
                        }
                    }
                }
            }
        }

        Response::send(array_merge([
            'xchan_hash'   => $xchan['xchan_hash'],
            // Every hash this identity is known by. Items are stored under
            // whichever row delivered them (the zot6 row for a channel also
            // seen over ActivityPub), so anything filtering by author/owner
            // has to match them all, not just the first row.
            'xchan_hashes' => array_values(array_unique(array_column($xchans, 'xchan_hash'))),
            'name'         => Response::decodeEntities($xchan['xchan_name']),
            'address'      => $xchan['xchan_addr'],
            'url'          => $xchan['xchan_url'],
            'photo'        => $xchan['xchan_photo_l'] ?: ($xchan['xchan_photo_m'] ?? ''),
            'network'      => $xchan['xchan_network'],
            'is_forum'     => (bool) intval($xchan['xchan_pubforum'] ?? 0),
            'is_connected' => $is_connected,
            'abook_id'     => $abook_id,
            'local_nick'   => $local_nick,
            'actor_fields' => $actor_fields,
        ], $profile_data));
    }

    /**
     * Names/photos for a set of xchan hashes, for labelling ACL chips.
     *
     * Owner-only: the caller is editing their own audience, and this would
     * otherwise let anyone turn a hash into a name. The owner's own hash is
     * included on purpose — /acl excludes abook_self, so it is the one entry
     * an audience list can never resolve from the connections endpoint.
     */
    private function names(string $csv): void
    {
        \Utsukta\SpaCore\Api\Auth::requireLocalGet();

        $hashes = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $csv))
        )));
        if (!$hashes) Response::send([]);
        // Bounded so a hand-crafted query string can't ask for the whole table.
        $hashes = array_slice($hashes, 0, 100);

        $in = "'" . implode("','", array_map('dbesc', $hashes)) . "'";
        $rows = q("SELECT xchan_hash, xchan_name, xchan_addr, xchan_photo_m
                   FROM xchan WHERE xchan_hash IN ($in)");

        Response::send(array_map(fn($r) => [
            'xid'   => $r['xchan_hash'],
            'name'  => Response::decodeEntities($r['xchan_name']),
            'link'  => $r['xchan_addr'],
            'photo' => $r['xchan_photo_m'],
        ], $rows ?: []));
    }

}
