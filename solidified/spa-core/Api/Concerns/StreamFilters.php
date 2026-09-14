<?php
// packages/spa-core/php/Api/Concerns/StreamFilters.php

namespace Utsukta\SpaCore\Api\Concerns;

use Utsukta\SpaCore\Api\Response;

/**
 * The stream filter query params, turned into SQL.
 *
 * Moved here verbatim from Network.php so the inbox can be filtered by the
 * same params: the network sidebar widget writes them to the URL, the inbox's
 * search box parses them out of `from:`/`is:`/`in:` operators (see
 * src/modules/inbox/query.ts), and both /spa/network and /spa/hq-messages read
 * the same set. A second copy of this would be a second set of privacy rules to
 * keep in step — the `$dismiss_privacy_filter` interaction below is exactly the
 * kind of thing that goes wrong when it is duplicated.
 *
 * Every clause is written against `$ctx['alias']`, so a handler that selects
 * `FROM item i` gets `i.` and one that selects `FROM item` gets `item.` —
 * rather than the caller string-replacing a prefix afterwards, which would
 * also rewrite a search term that happens to contain "item.".
 *
 * A caller that passes `cmin`/`cmax` through must join abook as Network does
 * (`LEFT JOIN abook ON (<alias>.owner_xchan = abook.abook_xchan AND
 * abook.abook_channel = <uid>)`); `needs_abook` in the result says whether the
 * returned SQL actually references it.
 */
class StreamFilters
{
    /**
     * @param array $q   The query params ($_GET).
     * @param array $ctx alias, channel_hash, observer_xchan, item_normal,
     *                   extra (SQL to seed $sql_extra with), flat (seed).
     * @return array{extra:string,options:string,nets:string,date:string,
     *               thread_top:string,flat:bool,net_query:string,
     *               net_query2:string,datequery:string,needs_abook:bool}
     */
    public static function build(array $q, int $uid, array $ctx): array
    {
        $a = $ctx['alias'] ?? 'item';
        $channel_hash = $ctx['channel_hash'] ?? '';
        $observer_xchan = $ctx['observer_xchan'] ?? '';
        $item_normal = $ctx['item_normal'] ?? '';

        $star = intval($q['star'] ?? 0);
        $liked = intval($q['liked'] ?? 0);
        $conv = intval($q['conv'] ?? 0);
        $dm = intval($q['dm'] ?? 0);
        $spam = intval($q['spam'] ?? 0);
        $nouveau = ($ctx['flat'] ?? false) || (bool) intval($q['nouveau'] ?? 0);
        $unseen = $q['unseen'] ?? '';
        $pf = intval($q['pf'] ?? 0);
        $gid = intval($q['gid'] ?? 0);
        $cid = intval($q['cid'] ?? 0);
        $xchan = $q['xchan'] ?? '';
        $net = $q['net'] ?? '';
        $search = $q['search'] ?? '';
        $hashtags = $q['tag'] ?? '';
        $category = $q['cat'] ?? '';
        $verb = $q['verb'] ?? '';
        $file = $q['file'] ?? '';
        $has = $q['has'] ?? '';

        $datequery = (isset($q['dend']) && is_a_date_arg($q['dend']))
            ? notags($q['dend'])
            : '';
        $datequery2 = (isset($q['dbegin']) && is_a_date_arg($q['dbegin']))
            ? notags($q['dbegin'])
            : '';

        // Affinity (disabled when app not installed → -1)
        $cmin = array_key_exists('cmin', $q) ? intval($q['cmin']) : -1;
        $cmax = array_key_exists('cmax', $q) ? intval($q['cmax']) : -1;

        // Hashtag shorthand in search
        if ($search && str_starts_with($search, '#')) {
            $hashtags = substr($search, 1);
            $search = '';
        }

        // Filters that force nouveau (flat) mode — forum/channel (cid), group (gid),
        // and xchan filters intentionally stay threaded (posts only) unless the user
        // explicitly picks order=unthreaded; only these filters force a flat listing.
        if ($search || $file || $hashtags || $verb || $category || $conv || $unseen) {
            $nouveau = true;
        }

        // ── SQL fragments ─────────────────────────────────────────────────────
        $sql_options = $star ? ' and item_starred = 1 ' : '';
        $sql_extra = $ctx['extra'] ?? '';
        $item_thread_top = " AND $a.item_thread_top = 1 ";

        // Privacy group
        if ($gid) {
            $r = q('SELECT * FROM pgrp WHERE id = %d AND uid = %d LIMIT 1',
                intval($gid), $uid);
            if (!$r) {
                Response::error(404, 'No such group');
            }
            $group_hash = $r[0]['hash'];
            $contacts = \Zotlabs\Lib\AccessList::members($uid, $gid);
            $contact_str = $contacts ? ids_to_querystr($contacts, 'xchan', true) : " '0' ";

            $item_thread_top = '';
            $sql_extra .= " AND $a.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE true $sql_options
                AND (( author_xchan IN ($contact_str) OR owner_xchan IN ($contact_str))
                     OR allow_gid LIKE '" . protect_sprintf('%<' . dbesc($group_hash) . '>%') . "')
                AND id = parent $item_normal
            ) ";
        }

        // Abook contact
        if ($cid) {
            $cid_r = q('SELECT abook_xchan FROM abook
                        WHERE abook_id = %d AND abook_channel = %d AND abook_blocked = 0 LIMIT 1',
                intval($cid), $uid);
            if (!$cid_r) {
                Response::error(404, 'No such channel');
            }
            $cid_xchan = $cid_r[0]['abook_xchan'];
            $item_thread_top = '';

            $sql_extra .= " AND $a.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE uid = $uid
                AND ( author_xchan = '" . dbesc($cid_xchan) . "'
                   OR owner_xchan  = '" . dbesc($cid_xchan) . "')
                $item_normal
            ) ";
        }

        // xchan — comma-separated: one identity can own several xchan rows
        // (a zot6 channel also known over ActivityPub), and its items sit
        // under whichever hash delivered them.
        if ($xchan) {
            $hashes = array_filter(array_map('trim', explode(',', $xchan)));
            $in = "'" . implode("','", array_map('dbesc', $hashes)) . "'";
            // Their own posts only. Matching the thread instead ($a.parent IN
            // …) also drags in every conversation they merely commented on.
            $sql_extra .= " AND ( $a.author_xchan IN ($in) OR $a.owner_xchan IN ($in) ) ";
        }

        // Category / hashtag / search / verb / file
        if ($category) {
            $sql_extra .= protect_sprintf(term_query($a, $category, TERM_CATEGORY));
        }
        if ($hashtags) {
            $sql_extra .= protect_sprintf(term_query($a, $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG));
        }
        if ($search) {
            $sql_extra .= sprintf(
                " AND ($a.body LIKE '%s' OR $a.title LIKE '%s') ",
                dbesc(protect_sprintf('%' . $search . '%')),
                dbesc(protect_sprintf('%' . $search . '%'))
            );
        }
        if ($verb) {
            if (str_starts_with($verb, '.')) {
                $sql_extra .= sprintf(
                    " AND $a.obj_type = '%s' AND $a.verb IN ('Create','Update','Invite') ",
                    dbesc(protect_sprintf(substr($verb, 1)))
                );
            } else {
                $sql_extra .= sprintf(
                    " AND $a.verb = '%s' ",
                    dbesc(protect_sprintf($verb))
                );
            }
        }
        if ($file) {
            $sql_extra .= term_query($a, $file, TERM_FILE);
        }

        // has:attachment — `attach` is a JSON column that is empty rather than
        // null on most rows, and '[]' on a few, so all three have to be ruled out.
        if ($has === 'attachment') {
            $sql_extra .= " AND $a.attach IS NOT NULL AND $a.attach != '' AND $a.attach != '[]' ";
        }

        // Privacy fence.
        //
        // Presence means "the user actually filtered by this", so an empty
        // value does not count. The inbox sends `file=` and `author=` on every
        // request, and treating those as set dismissed the fence on every
        // call — which showed the whole stream under the Direct feed and
        // leaked DMs into the Inbox feed.
        $set = array_keys(array_filter(
            $q,
            fn($v) => is_array($v) ? $v !== [] : ($v !== '' && $v !== null)
        ));
        $dismiss_privacy_filter = array_intersect(
            ['cid', 'star', 'conv', 'file', 'verb', 'cat', 'search'],
            $set
        );
        if (!$dismiss_privacy_filter) {
            $sql_extra .= $dm
                ? " AND $a.item_private = 2 "
                : " AND $a.item_private IN (0, 1) ";
        }

        // Conversation (mentions + authored)
        if ($conv) {
            $item_thread_top = '';
            $sql_extra .= " AND ( author_xchan = '" . dbesc($channel_hash) . "'"
                . ' OR item_mentionsme = 1 ) ';
        }

        // Unseen
        if ($unseen) {
            $sql_extra .= ' AND item_unseen = 1 ';
        }

        // Liked threads
        if ($liked) {
            $item_thread_top = '';
            $sql_extra .= " AND $a.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE uid = $uid AND verb = 'Like'
                AND author_xchan = '" . dbesc($channel_hash) . "'
                $item_normal
            ) ";
        }

        // Spam
        if ($spam) {
            $sql_extra .= ' AND item_spam = 1 ';
        }

        // Followed threads (pf=1). Mirrors viewer_following in FormatsItems::
        // applyViewerFollowing() — an explicit Follow (with no later Ignore)
        // counts, and so does having commented on a thread with no explicit
        // Follow/Ignore at all, since core's own notifier already treats
        // authoring an item in a thread as opting into its notifications.
        if ($pf && $observer_xchan) {
            $obs = dbesc($observer_xchan);
            $sql_extra .= " AND $a.parent IN (
                SELECT f.parent
                FROM item f
                WHERE f.author_xchan = '$obs'
                  AND f.verb = 'Follow'
                  AND f.item_deleted = 0
                  AND NOT EXISTS (
                    SELECT 1 FROM item i
                    WHERE i.parent = f.parent
                      AND i.author_xchan = '$obs'
                      AND i.verb = 'Ignore'
                      AND i.item_deleted = 0
                      AND i.created > f.created
                  )
                UNION
                SELECT c.parent
                FROM item c
                WHERE c.uid = $uid
                  AND c.author_xchan = '$obs'
                  AND c.verb NOT IN ('Follow', 'Ignore')
                  AND c.item_deleted = 0
                  AND NOT EXISTS (
                    SELECT 1 FROM item i
                    WHERE i.parent = c.parent
                      AND i.author_xchan = '$obs'
                      AND i.verb IN ('Follow', 'Ignore')
                      AND i.item_deleted = 0
                  )
            ) ";
        }

        // Date range
        $sql_date = '';
        if ($datequery) {
            $sql_date .= " AND $a.created <= '"
                . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery)) . "' ";
        }
        if ($datequery2) {
            $sql_date .= " AND $a.created >= '"
                . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery2)) . "' ";
        }

        // Affinity
        $sql_nets = '';
        if ($cmin !== -1 || $cmax !== -1) {
            $sql_nets .= ' AND ';
            if ($cmax === 99)
                $sql_nets .= ' ( ';
            $sql_nets .= "( abook.abook_closeness >= $cmin AND abook.abook_closeness <= $cmax ) ";
            if ($cmax === 99)
                $sql_nets .= ' OR abook.abook_closeness IS NULL ) ';
        }

        // Network / protocol filter
        $net_query = $net ? " left join xchan on xchan_hash = $a.author_xchan " : '';
        $net_query2 = $net ? " and xchan_network = '" . protect_sprintf(dbesc($net)) . "' " : '';

        return [
            'extra' => $sql_extra,
            'options' => $sql_options,
            'nets' => $sql_nets,
            'date' => $sql_date,
            'thread_top' => $item_thread_top,
            'flat' => $nouveau,
            'net_query' => $net_query,
            'net_query2' => $net_query2,
            'datequery' => $datequery,
            'needs_abook' => $sql_nets !== '',
        ];
    }
}
