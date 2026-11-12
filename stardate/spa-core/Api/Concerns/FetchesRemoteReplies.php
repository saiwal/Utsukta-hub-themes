<?php

namespace Utsukta\SpaCore\Api\Concerns;

use Zotlabs\Lib\Activity;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\ASCache;
use Zotlabs\Lib\ASCollection;

/**
 * Discovers the replies to a remote ActivityPub object and stores them.
 *
 * This is Zotlabs\Daemon\Convo::run() minus the daemon shell: same
 * ASCollection → decode_note → Activity::store sequence, but bounded by a limit
 * and without the daemon's half-second-per-item throttle, which is fine for
 * background work and unacceptable inside a request.
 *
 * Activity::store() only ever *queues* a replies collection
 * (App::$cache['as_fetch_collection']) for that daemon, so without this an
 * imported post arrives with its ancestors but none of its discussion.
 *
 * Discovery is per-platform because the protocol doesn't settle it: Mastodon
 * and friends publish a `replies` collection on the object, Lemmy publishes
 * none at all (its Page has no `replies` key — its comments are only reachable
 * through its own API). See replyCandidates().
 *
 * ponytail: one level of replies per call, capped. Depth comes from calling it
 * again on the new leaves (see Item::fetchMoreReplies), not from recursion here.
 */
trait FetchesRemoteReplies
{
    /**
     * @param array  $obj      the AS object whose `replies` collection to walk
     * @param int    $limit    max replies to consider
     * @param float  $budget   seconds of wall clock to spend; 0 = no time bound
     *                         (a $limit of 0 is the off switch, not this)
     * @return int             number of activities stored
     */
    protected function fetchRemoteReplies(array $channel, string $observerHash, array $obj, int $limit, float $budget = 0): int
    {
        if ($limit <= 0) {
            return 0;
        }

        // Each uncached reply is its own signed round-trip to the origin server,
        // so a count alone doesn't bound how long this takes — a slow host turns
        // 15 replies into 20 seconds. The deadline is the bound that actually
        // matters when this runs inside a request.
        $deadline = $budget > 0 ? microtime(true) + $budget : 0;

        $messages = $this->replyCandidates($obj, $channel, $limit);
        if (!$messages) {
            return 0;
        }

        // Drop the ones we already hold. Each candidate we don't skip is a
        // remote fetch, so this is what makes a second press cheap and lets it
        // make progress instead of re-paying for the replies it already got.
        $messages = $this->rejectKnownMids($messages, $channel);
        if (!$messages) {
            return 0;
        }

        $stored = 0;

        foreach ($messages as $message) {
            if ($deadline && microtime(true) > $deadline) {
                break;
            }

            if (is_string($message)) {
                $data = ASCache::Get($message);
                if (!$data) {
                    $data = Activity::fetch($message, $channel);
                    if ($data) {
                        ASCache::Set($message, $data);
                    }
                }
            } else {
                $data = $message;
            }

            if (!is_array($data) || !$data) {
                continue;
            }

            $AS = new ActivityStreams($data);
            if (!$AS->is_valid() || !is_array($AS->obj) || ActivityStreams::is_an_actor($AS->obj)) {
                continue;
            }

            $item = Activity::decode_note($AS);
            if (!$item) {
                continue;
            }

            $item['item_fetched'] = true;
            Activity::store($channel, $observerHash, $AS, $item, false, true);
            $stored++;
        }

        return $stored;
    }

    /**
     * The ids (or inline objects) of an object's replies.
     *
     * Standard route is the AS `replies` collection. Lemmy publishes no such
     * collection on its Page objects, so a Lemmy post would otherwise import
     * with its whole discussion invisible — fall back to its API, which hands
     * back the ap_id of every comment in the thread in one request. Those ids
     * point at the commenters' home instances and go through the same
     * fetch/decode/store path as any other reply.
     */
    private function replyCandidates(array $obj, array $channel, int $limit): array
    {
        $repliesId = $obj['replies'] ?? null;
        if (is_array($repliesId)) {
            $repliesId = $repliesId['id'] ?? null;
        }

        if ($repliesId && is_string($repliesId)) {
            $messages = (new ASCollection($repliesId, $channel, 0, $limit))->get();
            if ($messages) {
                return $messages;
            }
        }

        return $this->lemmyCommentIds($obj, $limit);
    }

    /**
     * Comment ap_ids for a Lemmy post, or [] if this isn't one.
     *
     * Lemmy sorts by `Old` here on purpose: Activity::store() drops a comment
     * whose parent isn't stored yet (we walk with fetch_parents off), and
     * oldest-first puts every parent ahead of its children.
     */
    private function lemmyCommentIds(array $obj, int $limit): array
    {
        if (($obj['type'] ?? '') !== 'Page') {
            return [];
        }
        if (!preg_match('#^(https://[^/]+)/post/(\d+)#', $obj['id'] ?? '', $m)) {
            return [];
        }

        $url = $m[1] . '/api/v3/comment/list?type_=All&sort=Old&max_depth=8'
             . '&post_id=' . $m[2] . '&limit=' . intval($limit);

        $x = z_fetch_url($url, true);
        if (empty($x['success'])) {
            return [];
        }

        $j = json_decode($x['body'], true);
        if (!is_array($j) || empty($j['comments'])) {
            return [];
        }

        $ids = [];
        foreach ($j['comments'] as $c) {
            $apId = $c['comment']['ap_id'] ?? '';
            if ($apId && is_string($apId)) {
                $ids[] = $apId;
            }
        }

        return $ids;
    }

    /**
     * Candidates whose mid this channel doesn't already have. Inline objects
     * (not bare ids) pass through untouched — they cost no fetch anyway.
     */
    private function rejectKnownMids(array $messages, array $channel): array
    {
        $ids = array_values(array_filter($messages, 'is_string'));
        if (!$ids) {
            return $messages;
        }

        $in = implode(',', array_map(fn($m) => "'" . dbesc($m) . "'", $ids));
        $r  = q("select mid from item where uid = %d and mid in ($in)",
            intval($channel['channel_id'])
        );

        $known = array_column($r ?: [], 'mid');
        if (!$known) {
            return $messages;
        }

        return array_values(array_filter(
            $messages,
            fn($m) => !is_string($m) || !in_array($m, $known, true)
        ));
    }

    /**
     * The AS object for a mid, preferring the cache — after an import walk the
     * thread's objects are usually already warm from Activity::fetch().
     */
    protected function fetchActivityObject(string $mid, array $channel): array
    {
        $data = ASCache::Get($mid);
        if (!$data) {
            $data = Activity::fetch($mid, $channel);
            if ($data) {
                ASCache::Set($mid, $data);
            }
        }
        if (!is_array($data) || !$data) {
            return [];
        }

        $AS = new ActivityStreams($data);
        return ($AS->is_valid() && is_array($AS->obj)) ? $AS->obj : [];
    }
}
