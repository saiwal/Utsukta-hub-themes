<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Notes
{
    // POST /api/notes
    // Body (JSON): { body, mimetype? }
    // Creates a personal note directly via item_store(), bypassing the legacy
    // /item endpoint which would also create a blank companion Add activity.
    public function post(): void
    {
        require_once 'include/items.php';

        Auth::requireLocalJson();

        $uid      = local_channel();
        $channel  = \App::get_channel();
        $observer = \App::get_observer();
        $b        = Auth::$parsedBody;

        $content  = $b['body']     ?? '';
        $mimetype = $b['mimetype'] ?? 'text/bbcode';

        if (!trim($content)) {
            Response::error(400, 'Body is required');
        }

        // Extract #hashtags into term records for the tag-cloud widget. Notes
        // are always fully private with no ACL, so mentions/groups found by
        // linkify_tags() are discarded — only hashtags are persisted.
        $postTags    = [];
        $attachments = [];
        if ($mimetype === 'text/bbcode') {
            require_once 'include/text.php';
            $results = linkify_tags($content, $uid);
            foreach (($results ?: []) as $result) {
                $s = $result['success'];
                if ($s['replaced'] && (int) $s['termtype'] === TERM_HASHTAG) {
                    $postTags[] = [
                        'uid'   => $uid,
                        'ttype' => TERM_HASHTAG,
                        'otype' => TERM_OBJ_POST,
                        'term'  => $s['term'],
                        'url'   => $s['url'],
                    ];
                }
            }

            // Extract [attachment] tags → attach array, strip them from body
            // (same extraction Item.php's post/edit handlers use).
            if (preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/', $content, $match)) {
                require_once 'include/attach.php';
                foreach ($match[2] as $i => $mtch) {
                    $hash = substr($mtch, 0, strpos($mtch, ','));
                    $rev  = intval(substr($mtch, strpos($mtch, ',')));
                    $r    = attach_by_hash_nodata($hash, $observer['xchan_hash'], $rev);
                    if ($r['success']) {
                        $attachments[] = [
                            'url'      => z_root() . '/attach/' . $r['data']['hash'],
                            'length'   => $r['data']['filesize'],
                            'type'     => $r['data']['filetype'],
                            'title'    => urlencode($r['data']['filename']),
                            'revision' => $r['data']['revision'],
                        ];
                    }
                    $content = str_replace($match[1][$i], '', $content);
                }
            }
        }

        $uuid = item_message_id();
        $mid  = z_root() . '/item/' . $uuid;
        $now  = datetime_convert();

        $datarray = [
            'aid'             => $channel['channel_account_id'],
            'uid'             => $uid,
            'uuid'            => $uuid,
            'mid'             => $mid,
            'parent_mid'      => $mid,
            'thr_parent'      => $mid,
            'owner_xchan'     => $channel['channel_hash'],
            'author_xchan'    => $observer['xchan_hash'],
            'created'         => $now,
            'edited'          => $now,
            'commented'       => $now,
            'received'        => $now,
            'changed'         => $now,
            'verb'            => 'Create',
            'obj_type'        => 'Note',
            'item_type'       => ITEM_TYPE_CUSTOM,
            'mimetype'        => $mimetype,
            'body'            => $content,
            'plink'           => $mid,
            'allow_cid'       => '',
            'allow_gid'       => '',
            'deny_cid'        => '',
            'deny_gid'        => '',
            'attach'          => $attachments,
            'item_wall'       => 1,
            'item_origin'     => 1,
            'item_thread_top' => 1,
            'item_unseen'     => 0,
            'item_private'    => 1,
            'term'            => array_unique($postTags, SORT_REGULAR),
        ];

        // No federation, no delivery, no notifications
        $result = item_store($datarray, false, false, false);

        if (!$result['success']) {
            Response::error(500, 'Failed to save note');
        }

        Response::send(['mid' => $mid]);
    }

    // GET /api/notes
    // Lists the authenticated user's personal notes (ITEM_TYPE_CUSTOM items).
    // These never appear in streams or federate — the Notifier skips type != 0.
    // Query params: start (int, default 0), limit (int, default 20),
    //               tag (hashtag filter), dbegin/dend (YYYY-MM-DD date range),
    //               search (body text search)
    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/taxonomy.php';

        Auth::requireLocalGet();

        $uid   = local_channel();
        $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
        $start = max(0, intval($_GET['start'] ?? 0));

        // Use item_normal() with ITEM_TYPE_CUSTOM to get the correct SQL fragment
        $item_normal = item_normal(null, 'item', ITEM_TYPE_CUSTOM);

        $sql_extra = '';
        $tag = trim($_GET['tag'] ?? '');
        if ($tag) {
            $sql_extra .= protect_sprintf(term_query('item', $tag, TERM_HASHTAG));
        }

        $search = trim($_GET['search'] ?? '');
        if ($search) {
            $sql_extra .= sprintf(
                " AND item.body LIKE '%s' ",
                dbesc(protect_sprintf('%' . $search . '%'))
            );
        }

        $dend = (isset($_GET['dend']) && is_a_date_arg($_GET['dend']))
            ? notags($_GET['dend']) : '';
        $dbegin = (isset($_GET['dbegin']) && is_a_date_arg($_GET['dbegin']))
            ? notags($_GET['dbegin']) : '';

        if ($dend) {
            $sql_extra .= " AND item.created <= '"
                . dbesc(datetime_convert(date_default_timezone_get(), '', $dend)) . "' ";
        }
        if ($dbegin) {
            $sql_extra .= " AND item.created >= '"
                . dbesc(datetime_convert(date_default_timezone_get(), '', $dbegin)) . "' ";
        }

        $count_r = dbq(
            "SELECT COUNT(*) AS total FROM item
             WHERE item.uid = $uid
               AND item.item_thread_top = 1
               AND item.verb = 'Create'
               $item_normal $sql_extra"
        );
        $total = intval($count_r[0]['total'] ?? 0);

        $rows = dbq(
            "SELECT item.id, item.mid, item.uuid, item.body, item.title,
                    item.created, item.edited, item.mimetype, item.attach
             FROM item
             WHERE item.uid = $uid
               AND item.item_thread_top = 1
               AND item.verb = 'Create'
               $item_normal $sql_extra
             ORDER BY item.created DESC
             LIMIT $limit OFFSET $start"
        );

        $root = z_root();
        $items = [];
        foreach (($rows ?: []) as $row) {
            $attach = $row['attach'] ? (json_decode($row['attach'], true) ?: []) : [];
            $items[] = [
                'id'       => intval($row['id']),
                'mid'      => $row['mid'],
                'uuid'     => $row['uuid'],
                'body'     => $row['body'],
                'created'  => $row['created'],
                'edited'   => $row['edited'],
                'mimetype' => $row['mimetype'],
                'attach'   => array_map(function (array $a) use ($root): array {
                    $href = $a['href'] ?? '';
                    if ($href && str_starts_with($href, '/')) $href = $root . $href;
                    return [
                        'href'     => $href,
                        'type'     => $a['type']     ?? 'application/octet-stream',
                        'title'    => $a['title']    ?? '',
                        'length'   => (string) ($a['length']   ?? '0'),
                        'revision' => (string) ($a['revision'] ?? '0'),
                    ];
                }, $attach),
            ];
        }

        Response::paginate($items, $start, $limit, $total, false);
    }
}
