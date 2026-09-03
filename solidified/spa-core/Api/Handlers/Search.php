<?php
namespace Utsukta\SpaCore\Api\Handlers;

use App;
use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Concerns\FetchesRemoteReplies;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\Libzot;

class Search
{
    use FetchesRemoteReplies;

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
        $f = Libzot::fetch_conversation($channel, $url, true);

        if ($f) {
            // DReport rows carry a message_uuid even when the item was rejected
            // (permission denied / post ignored / storage failed), so confirm
            // against the DB rather than trusting the report.
            foreach ([$url, $f[0]['message_id'] ?? ''] as $mid) {
                if ($uuid = $this->storedUuid($mid, $channel)) {
                    Response::send(['uuid' => $uuid]);
                }
            }
        }

        // ── Path 2: ActivityPub (Mastodon, Pleroma, etc.) ─────────────────────
        // Mirrors pubcrawl_fetch_provider. The pubcrawl app supplies the
        // ActivityStreams Accept header via the get_accept_header_string hook —
        // without it Activity::fetch() gets HTML back and silently fails.
        if (!Apps::addon_app_installed($channel['channel_id'], 'pubcrawl')) {
            Response::error(404, 'Post not found — enable the ActivityPub app to import posts from Mastodon and other ActivityPub servers');
        }

        $j = Activity::fetch($url, $channel);
        if ($j) {
            $AS = new ActivityStreams($j);
            if ($AS->is_valid() && is_array($AS->obj) && !ActivityStreams::is_an_actor($AS->obj)) {
                $item = Activity::decode_note($AS);
                if ($item) {
                    $item['item_fetched'] = true;
                    Activity::store($channel, get_observer_hash(), $AS, $item, true, true);

                    // Activity::store() stores nothing and defers to a background
                    // parent fetch when the object is a reply whose thread root
                    // isn't local yet — true of most Mastodon permalinks. Walk and
                    // store the parent chain inline instead, as core does.
                    if (isset(App::$cache['as_fetch_objects'])) {
                        Activity::fetch_and_store_parents($channel, get_observer_hash(), $item, $AS, true);
                        unset(App::$cache['as_fetch_objects']);
                    }

                    Activity::init_background_fetch(get_observer_hash());

                    // Pull the discussion in too. Activity::store() only queues
                    // the replies collection for the background Convo daemon,
                    // so without this the post opens with no comments. Walk the
                    // thread root's replies, not the requested item's — for a
                    // reply permalink the interesting collection hangs off the
                    // root, and for a root URL the two are the same.
                    $row = $this->storedRow($item['mid'], $channel);
                    if ($row) {
                        $rootObj = $this->fetchActivityObject($row['parent_mid'], $channel);
                        if ($rootObj) {
                            // Bounded by wall clock, not just count: each reply
                            // is its own signed round-trip, so a busy thread on a
                            // slow host would otherwise run the request into a
                            // timeout. Whatever the budget doesn't reach stays
                            // queued for the Convo daemon, and the "fetch more
                            // replies" button (Item::fetchMoreReplies) picks up
                            // the rest on demand. import_replies_limit at 0
                            // disables the inline walk; import_replies_budget at
                            // 0 removes the time bound (count only).
                            $this->fetchRemoteReplies(
                                $channel,
                                get_observer_hash(),
                                $rootObj,
                                intval(Config::Get('spa', 'import_replies_limit', 25)),
                                floatval(Config::Get('spa', 'import_replies_budget', 6))
                            );
                        }
                        Response::send(['uuid' => $row['uuid']]);
                    }
                }
            }
        }

        Response::error(404, $this->explainFetchFailure($url));
    }

    /**
     * Why the fetch failed, in the user's words.
     *
     * Both fetch paths collapse every failure into null, so the generic
     * "not found" hid the two cases people actually hit: a deleted/private
     * post, and a server running authorized fetch that rejected our signed
     * request (commonly because this host's own actor URL isn't reachable from
     * theirs). One unsigned probe on the already-failed path tells them apart.
     */
    private function explainFetchFailure(string $url): string
    {
        $generic = 'Post not found — it may not be publicly accessible or may not support Zot/ActivityPub';

        $redirects = 0;
        $x = z_fetch_url($url, true, $redirects, [
            'headers' => ['Accept: application/activity+json'],
            'timeout' => 10,
        ]);

        $code = intval($x['return_code'] ?? 0);

        if ($code === 401 || $code === 403) {
            return 'The remote server refused the request (HTTP ' . $code . '). '
                 . 'It likely requires authorized fetch and could not verify this site — '
                 . 'check that this hub is reachable from the public internet.';
        }

        if ($code === 404 || $code === 410) {
            return 'The post no longer exists on the remote server (HTTP ' . $code . ').';
        }

        return $generic;
    }

    /**
     * The uuid of the row actually written for $mid in this channel, or '' if
     * the import didn't land. Both fetch paths can report success while storing
     * nothing, so this is the only trustworthy signal.
     */
    private function storedUuid(string $mid, array $channel): string
    {
        $r = $this->storedRow($mid, $channel);
        return $r ? $r['uuid'] : '';
    }

    private function storedRow(string $mid, array $channel): ?array
    {
        if (!$mid) {
            return null;
        }
        $r = q("select uuid, parent_mid from item where mid = '%s' and uid = %d limit 1",
            dbesc($mid),
            intval($channel['channel_id'])
        );
        return $r ? $r[0] : null;
    }
}
