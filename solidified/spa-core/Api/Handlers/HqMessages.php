<?php
/**
 * Utsukta\SpaCore\Api\Handlers\HqMessages
 *
 * GET /spa/hq-messages — feeds the HQ dashboard's message cards (all /
 * direct / starred / notifications / a filed folder). A from-scratch
 * reimplementation of Zotlabs/Widget/Messages.php::get_messages_page() +
 * get_notices_page(), kept independent of core so the SPA API can add
 * server-side `search` filtering (a name/address LIKE match) without
 * touching deployed core.
 */

namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\Concerns\FormatsItems;
use Utsukta\SpaCore\Api\Concerns\StreamFilters;
use Utsukta\SpaCore\Api\Concerns\FilesByRules;

require_once 'include/items.php';
require_once 'include/text.php';
require_once 'include/html2plain.php';
require_once 'include/bbcode.php';

class HqMessages
{
    use FormatsItems;
    use FilesByRules;

    /** File-tag folder the inbox uses as its trash can. Items tagged with it
     *  are hidden from every feed except the Trash folder view itself — the
     *  inbox's delete is a move, so it stays undoable and federates nothing. */
    public const TRASH = 'Trash';

    /**
     * What counts as a listable message, for `$alias`.
     *
     * Shared with Folders.php so the sidebar's unread badges can't disagree
     * with the lists they sit next to — leaving the verb exclusions off the
     * count made a channel with 9 DM threads report 13 unread ones, because
     * internal Follow/Ignore/Add/Remove activities are rows in `item` too.
     */
    public static function itemNormalSql(int $uid, string $alias = 'item'): string
    {
        $sql = item_normal($uid)
            . " and item.verb not in ('Add', 'Remove', 'Follow', 'Ignore', '" . ACTIVITY_FOLLOW . "') ";
        return $alias === 'item' ? $sql : str_replace('item.', $alias . '.', $sql);
    }

    public function get(): void
    {
        $uid = Auth::requireLocalGet();

        $offset = max(0, intval($_GET['offset'] ?? 0));
        $type = $_GET['type'] ?? '';
        $file = $_GET['file'] ?? '';
        // `search` is the shared stream filter (body/title, see StreamFilters);
        // `author` is this endpoint's own name/address filter, which is what the
        // list header's box and HQ's message cards use.
        $author = trim($_GET['author'] ?? '');
        $xchan  = trim($_GET['xchan'] ?? '');
        $unread = ($_GET['unread'] ?? '') === '1';

        if ($type === 'notification') {
            $this->sendNotices($uid, $offset, $author);
            return;
        }

        $limit = 30;

        // Pass $uid so item_normal() recognizes the requester as owner and
        // includes their own delayed/moderated items (see Channel.php).
        $item_normal = self::itemNormalSql($uid);
        $item_normal_i = self::itemNormalSql($uid, 'i');
        $item_normal_c = self::itemNormalSql($uid, 'c');

        $vnotify = get_pconfig($uid, 'system', 'vnotify', -1);
        $vnotify_sql_c = '';
        if (!($vnotify & VNOTIFY_LIKE)) {
            $vnotify_sql_c = " AND c.verb NOT IN ('Like', 'Dislike', '" . dbesc(ACTIVITY_LIKE) . "', '" . dbesc(ACTIVITY_DISLIKE) . "') ";
        } elseif (!feature_enabled($uid, 'dislike')) {
            $vnotify_sql_c = " AND c.verb NOT IN ('Dislike', '" . dbesc(ACTIVITY_DISLIKE) . "') ";
        }

        // Free-text search against the author's display name/address.
        $search_sql = '';
        if ($author !== '') {
            $search_like = protect_sprintf(dbesc('%' . str_replace(['%', '_'], ['\\%', '\\_'], $author) . '%'));
            $search_sql = " AND EXISTS (
                SELECT 1 FROM xchan sx WHERE sx.xchan_hash = i.author_xchan
                AND (sx.xchan_name LIKE '$search_like' OR sx.xchan_addr LIKE '$search_like')
            ) ";
        }

        // Conversation with one channel: threads they wrote, plus the ones we
        // wrote to them (where we're the author and they're in the ACL).
        // allow_cid stores hashes as '<hash>', and a hash is base64url so it
        // carries no LIKE wildcards.
        // Comma-separated: one identity can own several xchan rows (see
        // Xchan.php's xchan_hashes). protect_sprintf keeps the LIKE wildcards
        // alive through the sprintf inside q().
        $xchan_sql = '';
        if ($xchan !== '') {
            $hashes = array_filter(array_map('trim', explode(',', $xchan)));
            $in = "'" . implode("','", array_map('dbesc', $hashes)) . "'";
            $likes = [];
            foreach ($hashes as $h) {
                $likes[] = "i.allow_cid LIKE '" . protect_sprintf('%<' . dbesc($h) . '>%') . "'";
            }
            $xchan_sql = " AND ( i.author_xchan IN ($in) OR " . implode(' OR ', $likes) . " ) ";
        }

        $dummy_order_sql = '';
        // Sort by last thread activity ("commented", bumped by item_store()
        // on every new reply) rather than thread-creation time, so a DM
        // thread that just got a new reply bubbles back to the top.
        $order_col = 'created';

        // The selected feed is expressed as stream-filter params rather than
        // hand-rolled SQL, so the sidebar filter widget and the feed can't
        // disagree about privacy: `dm` and `star` already carry the right
        // rules (and `star`/`file` deliberately dismiss the public/private
        // fence, exactly as they do on /network).
        $q = $_GET;
        switch ($type) {
            case 'direct':
                $q['dm'] = '1';
                $order_col = 'commented';
                // Tricks some mysql backends into using the right index.
                $dummy_order_sql = ', i.received DESC ';
                break;
            case 'starred':
                $q['star'] = '1';
                break;
            case 'filed':
                if ($file) {
                    $q['file'] = $file;
                }
                break;
        }

        $channel = \App::get_channel();
        $f = StreamFilters::build($q, $uid, [
            'alias' => 'i',
            'channel_hash' => $channel['channel_hash'] ?? '',
            'observer_xchan' => get_observer_hash(),
            'item_normal' => $item_normal,
        ]);

        // A mailbox lists threads, so the thread-top restriction stays even for
        // the filters that relax it on /network (conv, liked, cid, gid). Those
        // are all `parent IN (…)` subqueries and so still match thread-wide;
        // the row-level ones (search, tag, unseen) match the first message of
        // the thread rather than any reply in it.
        $type_sql = ' AND i.item_thread_top = 1 ' . $f['extra'] . $f['options'] . $f['nets'] . $f['date'];

        // Inbox filter chip. Distinct from the shared `unseen` filter, which is
        // row-level: an inbox thread counts as unread when a *reply* is unseen.
        if ($unread) {
            $type_sql .= " AND (i.item_unseen = 1 OR EXISTS (
                SELECT 1 FROM item cu WHERE cu.uid = i.uid AND cu.parent = i.parent
                AND cu.item_unseen = 1 AND cu.item_thread_top = 0
            )) ";
        }

        // Trashed items drop out of every feed but the Trash folder itself —
        // reached either by selecting the folder or by an `in:trash` search.
        $viewingTrash = ($type === 'filed' && $file === self::TRASH)
            || (($q['file'] ?? '') === self::TRASH);
        if (!$viewingTrash) {
            $type_sql .= " AND i.id NOT IN (SELECT oid FROM term
                WHERE ttype = " . intval(TERM_FILE) . " AND uid = i.uid
                AND term = '" . protect_sprintf(dbesc(self::TRASH)) . "') ";
        }

        // Affinity filtering reads abook; nothing else here needs the join.
        $abook_join = $f['needs_abook']
            ? " LEFT JOIN abook ON (i.owner_xchan = abook.abook_xchan AND abook.abook_channel = $uid) "
            : '';
        // Protocol filter brings its own xchan join.
        $net_join = $f['net_query'];
        $type_sql .= $f['net_query2'];

        $items = q("SELECT i.*,
            (SELECT count(*) FROM item c WHERE c.uid = %d AND c.parent = i.parent AND c.item_unseen = 1 AND c.item_thread_top = 0 $item_normal_c $vnotify_sql_c) AS unseen_count
            FROM item i
            $abook_join
            $net_join
            WHERE i.uid = %d
            AND i.created <= '%s'
            $type_sql
            $search_sql
            $xchan_sql
            $item_normal_i
            ORDER BY i.$order_col DESC $dummy_order_sql
            LIMIT $limit OFFSET $offset",
            intval($uid),
            intval($uid),
            dbescdate(datetime_convert())
        );

        // Every entry carries its folder list, not just the filed feed — the
        // inbox renders folder chips and needs to know what a move must undo.
        $items = fetch_post_tags($items);

        xchan_query($items, false);

        // Auto-file by the user's inbox rules. After xchan_query, which is what
        // puts the author's name and address on the row for the sender rules.
        // Only the unfiltered first page: the cursor may only advance past posts
        // we have actually all seen, and any filter or offset makes this page a
        // subset of what arrived.
        if ($type === '' && $offset === 0 && $search_sql === '' && $xchan_sql === ''
            && !$unread && $author === '' && ($q['file'] ?? '') === '') {
            $this->applyInboxRules($items, $uid);
        }

        $entries = [];

        foreach ($items as $item) {
            $hook_data = [
                'uid' => $item['uid'],
                'owner_xchan' => $item['owner_xchan'],
                'author_xchan' => $item['author_xchan'],
                'cancel' => false,
            ];
            call_hooks('messages_widget', $hook_data);
            if ($hook_data['cancel']) {
                continue;
            }

            $info = '';
            if ($type === 'direct') {
                $info .= $this->dmRecipients($item);
            }

            // Who put this in the stream, when that isn't the author: the
            // resharer, or the group/forum it came through. Sent as its own
            // field as well as inside $info — $info is a rendered string the
            // client can only display verbatim, and the 'filed' branch below
            // overwrites it entirely.
            $via = '';
            if ($item['owner_xchan'] !== $item['author_xchan']) {
                $via = Response::decodeEntities($item['owner']['xchan_name']);
            } elseif ($item['verb'] === 'Announce' && isset($item['source'])) {
                $via = Response::decodeEntities($item['source']['xchan_name']);
            }
            if ($via !== '') {
                $info .= t('via') . ' ' . $via;
            }

            $folders = [];
            foreach (($item['term'] ?? []) as $term) {
                if (intval($term['ttype']) === TERM_FILE) {
                    $folders[] = $term['term'];
                }
            }

            if ($type === 'filed') {
                $info = '';
                foreach ($folders as $name) {
                    $info .= '<span class="badge rounded-pill bg-danger me-1"><i class="bi bi-folder"></i>&nbsp;' . $name . '</span>';
                }
            }

            // The title is a subject line (a DM's, most often) — sent as its
            // own field so the list can show it above the body excerpt rather
            // than in place of it.
            $title = $item['title'] ? substr_words($item['title'], 140) : '';

            $summary = $item['summary'];
            if (!$summary) {
                $summary = html2plain(bbcode($item['body'], ['drop_media' => true, 'tryoembed' => false]), 75, true);
                if ($summary) {
                    $summary = htmlentities($summary, ENT_QUOTES, 'UTF-8', false);
                }
            }
            if (!$summary) {
                $summary = $title ? '' : '...';
            } else {
                $summary = substr_words($summary, 140);
            }

            switch (intval($item['item_private'])) {
                case 1:
                    $icon = '<i class="bi bi-lock"></i>';
                    break;
                case 2:
                    $icon = '<i class="bi bi-envelope"></i>';
                    break;
                default:
                    $icon = '';
            }

            $entries[] = [
                'author_name' => Response::decodeEntities($item['author']['xchan_name']),
                'author_addr' => $item['author']['xchan_addr'] ?: $item['author']['xchan_url'],
                'author_img' => $item['author']['xchan_photo_s'],
                'info' => $info,
                'created' => datetime_convert('UTC', date_default_timezone_get(), $item[$order_col]),
                'title' => $title,
                'summary' => $summary,
                'b64mid' => $item['uuid'],
                'href' => z_root() . '/hq/' . $item['uuid'],
                'icon' => $icon,
                'unseen_count' => $item['unseen_count'] ?: ($item['item_unseen'] ? '&#8192;' : ''),
                'unseen_class' => $item['item_unseen'] ? 'primary' : 'secondary',
                'unseen' => (bool) intval($item['item_unseen']),
                'starred' => (bool) intval($item['item_starred']),
                'folders' => $folders,
                'via' => $via,
            ];
        }

        Response::send($entries, [
            'offset' => count($entries) < $limit ? -1 : $offset + $limit,
        ]);
    }

    private function sendNotices(int $uid, int $offset, string $search): void
    {
        $limit = 30;

        $search_sql = '';
        if ($search !== '') {
            $search_like = protect_sprintf(dbesc('%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%'));
            $search_sql = " AND (notify.xname LIKE '$search_like' OR hubloc.hubloc_addr LIKE '$search_like') ";
        }

        $notices = q("SELECT notify.*, max(hubloc.hubloc_addr) as hubloc_addr FROM notify
            LEFT JOIN hubloc ON notify.url = hubloc.hubloc_id_url
            WHERE notify.uid = %d $search_sql AND hubloc.hubloc_primary = 1
            GROUP BY notify.id ORDER BY notify.created DESC LIMIT $limit OFFSET $offset",
            intval($uid)
        );

        $entries = [];

        foreach ($notices as $notice) {
            $summary = trim(strip_tags(bbcode($notice['msg'])));
            if (strpos($summary, $notice['xname']) === 0) {
                $summary = substr($summary, strlen($notice['xname']) + 1);
            }

            $isIntro = (bool) ($notice['ntype'] & NOTIFY_INTRO);
            $hashLink = str_contains($notice['hash'], '-') ? $notice['hash'] : basename($notice['link']);

            $entries[] = [
                'author_name' => Response::decodeEntities($notice['xname']),
                'author_addr' => $notice['hubloc_addr'],
                'author_img' => $notice['photo'],
                'info' => '',
                'created' => datetime_convert('UTC', date_default_timezone_get(), $notice['created']),
                'title' => '',
                'summary' => $summary,
                'b64mid' => $isIntro ? '' : $hashLink,
                'href' => $isIntro ? $notice['link'] : z_root() . '/hq/' . $hashLink,
                'icon' => $isIntro ? '<i class="bi bi-person-plus"></i>' : '',
                'unseen_count' => '',
                'unseen_class' => 'secondary',
            ];
        }

        Response::send($entries, [
            'offset' => count($entries) < $limit ? -1 : $offset + $limit,
        ]);
    }
}
