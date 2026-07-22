<?php
/**
 * Theme\Solidified\Api\Handlers\HqMessages
 *
 * GET /spa/hq-messages — feeds the HQ dashboard's message cards (all /
 * direct / starred / notifications / a filed folder). A from-scratch
 * reimplementation of Zotlabs/Widget/Messages.php::get_messages_page() +
 * get_notices_page(), kept independent of core so the SPA API can add
 * server-side `search` filtering (a name/address LIKE match) without
 * touching deployed core.
 */

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Theme\Solidified\Api\Concerns\FormatsItems;

require_once 'include/items.php';
require_once 'include/text.php';
require_once 'include/html2plain.php';
require_once 'include/bbcode.php';

class HqMessages
{
    use FormatsItems;

    public function get(): void
    {
        $uid = Auth::requireLocalGet();

        $offset = max(0, intval($_GET['offset'] ?? 0));
        $type = $_GET['type'] ?? '';
        $file = $_GET['file'] ?? '';
        $search = trim($_GET['search'] ?? '');

        if ($type === 'notification') {
            $this->sendNotices($uid, $offset, $search);
            return;
        }

        $limit = 30;

        $item_normal = item_normal();
        // Filter internal follow activities and stream add/remove activities.
        $item_normal .= " and item.verb not in ('Add', 'Remove', 'Follow', 'Ignore', '" . ACTIVITY_FOLLOW . "') ";
        $item_normal_i = str_replace('item.', 'i.', $item_normal);
        $item_normal_c = str_replace('item.', 'c.', $item_normal);

        $vnotify = get_pconfig($uid, 'system', 'vnotify', -1);
        $vnotify_sql_c = '';
        if (!($vnotify & VNOTIFY_LIKE)) {
            $vnotify_sql_c = " AND c.verb NOT IN ('Like', 'Dislike', '" . dbesc(ACTIVITY_LIKE) . "', '" . dbesc(ACTIVITY_DISLIKE) . "') ";
        } elseif (!feature_enabled($uid, 'dislike')) {
            $vnotify_sql_c = " AND c.verb NOT IN ('Dislike', '" . dbesc(ACTIVITY_DISLIKE) . "') ";
        }

        $filed_filter_sql = '';
        if ($type === 'filed' && $file) {
            $filed_filter_sql = " AND (term.term = '" . protect_sprintf(dbesc($file)) . "') ";
        }

        // Free-text search against the author's display name/address.
        $search_sql = '';
        if ($search !== '') {
            $search_like = protect_sprintf(dbesc('%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%'));
            $search_sql = " AND EXISTS (
                SELECT 1 FROM xchan sx WHERE sx.xchan_hash = i.author_xchan
                AND (sx.xchan_name LIKE '$search_like' OR sx.xchan_addr LIKE '$search_like')
            ) ";
        }

        $dummy_order_sql = '';
        switch ($type) {
            case 'direct':
                $type_sql = ' AND i.item_private = 2 AND i.item_thread_top = 1 ';
                // Tricks some mysql backends into using the right index.
                $dummy_order_sql = ', i.received DESC ';
                break;
            case 'starred':
                $type_sql = ' AND i.item_starred = 1 AND i.item_thread_top = 1 ';
                break;
            case 'filed':
                $type_sql = ' AND i.id IN (SELECT term.oid FROM term WHERE term.ttype = ' . TERM_FILE . ' AND term.uid = i.uid ' . $filed_filter_sql . ')';
                break;
            default:
                $type_sql = ' AND i.item_private IN (0, 1) AND i.item_thread_top = 1 ';
        }

        $items = q("SELECT *,
            (SELECT count(*) FROM item c WHERE c.uid = %d AND c.parent = i.parent AND c.item_unseen = 1 AND c.item_thread_top = 0 $item_normal_c $vnotify_sql_c) AS unseen_count
            FROM item i
            WHERE i.uid = %d
            AND i.created <= '%s'
            $type_sql
            $search_sql
            $item_normal_i
            ORDER BY i.created DESC $dummy_order_sql
            LIMIT $limit OFFSET $offset",
            intval($uid),
            intval($uid),
            dbescdate(datetime_convert())
        );

        if ($type === 'filed') {
            $items = fetch_post_tags($items);
        }

        xchan_query($items, false);

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

            if ($item['owner_xchan'] !== $item['author_xchan']) {
                $info .= t('via') . ' ' . $item['owner']['xchan_name'];
            } elseif ($item['verb'] === 'Announce' && isset($item['source'])) {
                $info .= t('via') . ' ' . $item['source']['xchan_name'];
            }

            if ($type === 'filed') {
                $info = '';
                foreach ($item['term'] as $term) {
                    if ($term['ttype'] !== TERM_FILE) {
                        continue;
                    }
                    $info .= '<span class="badge rounded-pill bg-danger me-1"><i class="bi bi-folder"></i>&nbsp;' . $term['term'] . '</span>';
                }
            }

            $summary = $item['title'];
            if (!$summary) {
                $summary = $item['summary'];
            }
            if (!$summary) {
                $summary = html2plain(bbcode($item['body'], ['drop_media' => true, 'tryoembed' => false]), 75, true);
                if ($summary) {
                    $summary = htmlentities($summary, ENT_QUOTES, 'UTF-8', false);
                }
            }
            if (!$summary) {
                $summary = '...';
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
                'author_name' => $item['author']['xchan_name'],
                'author_addr' => $item['author']['xchan_addr'] ?: $item['author']['xchan_url'],
                'author_img' => $item['author']['xchan_photo_s'],
                'info' => $info,
                'created' => datetime_convert('UTC', date_default_timezone_get(), $item['created']),
                'summary' => $summary,
                'b64mid' => $item['uuid'],
                'href' => z_root() . '/hq/' . $item['uuid'],
                'icon' => $icon,
                'unseen_count' => $item['unseen_count'] ?: ($item['item_unseen'] ? '&#8192;' : ''),
                'unseen_class' => $item['item_unseen'] ? 'primary' : 'secondary',
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
                'author_name' => $notice['xname'],
                'author_addr' => $notice['hubloc_addr'],
                'author_img' => $notice['photo'],
                'info' => '',
                'created' => datetime_convert('UTC', date_default_timezone_get(), $notice['created']),
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
