<?php
namespace Theme\Solidified\Api\Handlers;

use App;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Libzot;

class Search
{
    public function get(): void
    {
        Auth::requireLocalGet();

        $url = trim($_GET['url'] ?? '');

        if (!$url || !str_starts_with($url, 'https://')) {
            Response::error(400, 'A valid https:// URL is required');
        }

        // Handle b64-encoded message IDs (mirrors core Search.php)
        if (str_contains($url, 'b64.')) {
            if (str_contains($url, '?')) {
                $url = strtok($url, '?');
            }
            $url = unpack_link_id(basename($url));
            if ($url === false) {
                Response::error(400, 'Malformed b64 URL');
            }
        }

        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            $parsed['host'] = punify($parsed['host']);
            $url = unparse_url($parsed);
        }

        $channel = App::get_channel();

        // ── Path 1: Zot protocol (Hubzilla-to-Hubzilla) ───────────────────────
        // fetch_conversation may return [] on first encounter with a remote actor
        // because import_author_xchan hasn't registered the hubloc yet.
        // A single retry is enough once the actor is in the DB.
        for ($i = 0; $i < 2; $i++) {
            $f = Libzot::fetch_conversation($channel, $url, true);
            if ($f) break;
        }

        if ($f) {
            $uuid = $f[0]['message_uuid'] ?? '';
            foreach ($f as $m) {
                if (($m['message_id'] ?? '') === $url && !empty($m['message_uuid'])) {
                    $uuid = $m['message_uuid'];
                    break;
                }
            }
            if ($uuid) {
                Response::send(['uuid' => $uuid]);
            }
        }

        // ── Path 2: ActivityPub (Mastodon, Pleroma, etc.) ─────────────────────
        // Mirrors pubcrawl_fetch_provider: fetch the AP object, decode, store, return uuid.
        $j = Activity::fetch($url, $channel);
        if ($j) {
            $AS = new ActivityStreams($j);
            if ($AS->is_valid() && is_array($AS->obj) && !ActivityStreams::is_an_actor($AS->obj)) {
                $item = Activity::decode_note($AS);
                if ($item) {
                    $item['item_fetched'] = true;
                    Activity::store($channel, get_observer_hash(), $AS, $item, true, true);
                    if (!empty($item['uuid'])) {
                        Response::send(['uuid' => $item['uuid']]);
                    }
                }
            }
        }

        Response::error(404, 'Post not found — it may not be publicly accessible or may not support Zot/ActivityPub');
    }
}
