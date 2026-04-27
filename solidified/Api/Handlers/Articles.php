<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

class Articles
{
    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/conversation.php';
        require_once 'include/acl_selectors.php';

        $nick = \App::$argv[2] ?? '';
        if (!$nick) {
            Response::error(400, 'Channel nick required');
        }

        $channel = channelx_by_nick($nick, true);
        if (!$channel || $channel['channel_removed']) {
            Response::error(404, 'Channel not found');
        }

        $profile_uid = intval($channel['channel_id']);
        $observer    = \App::get_observer();
        $ob_hash     = $observer ? $observer['xchan_hash'] : '';
        $perms       = get_all_perms($profile_uid, $ob_hash);

        if (!$perms['view_pages']) {
            Response::error(403, 'Permission denied');
        }

        $permission_sql = item_permissions_sql($profile_uid);
$slug = \App::$argv[3] ?? $_GET['uuid'] ?? '';
if ($slug) {
    $this->getSingle($slug, $profile_uid, $ob_hash, $permission_sql);
}

        $this->getList($profile_uid, $ob_hash, $permission_sql);
    }

    // -------------------------------------------------------------------------
    // GET /api/articles/:nick/:uuid
    // -------------------------------------------------------------------------

    private function getSingle(
        string $slug,
        int    $profile_uid,
        string $ob_hash,
        string $permission_sql
    ): never {
        $uuid_safe = dbesc($slug);

        $r = dbq("SELECT id FROM item
            WHERE item.uid = $profile_uid
            AND item.uuid = '$uuid_safe'
            AND item.item_type = " . ITEM_TYPE_ARTICLE . "
            AND item.item_deleted = 0
            LIMIT 1");

        if (!$r) {
            Response::error(404, 'Article not found');
        }

        $iid = intval($r[0]['id']);

        $items = dbq("SELECT item.*, " . $this->reactionSubqueries() . "
            FROM item
            WHERE item.uid = $profile_uid
            AND (
                item.id = $iid
                OR (item.parent = $iid AND item.verb != 'Add')
            )
            AND item.item_deleted = 0
            $permission_sql
            ORDER BY item.created ASC");

        if (!$items) {
            Response::error(404, 'Thread not found');
        }

        xchan_query($items, true);
        $items = fetch_post_tags($items, true);

        $root     = null;
        $comments = [];

        foreach ($items as $item) {
            if (intval($item['item_thread_top'])) {
                $root = $this->formatItem($item, $ob_hash);
            } else {
                $comments[] = $this->formatItem($item, $ob_hash);
            }
        }

        if (!$root) {
            Response::error(404, 'Root item not found');
        }

        Response::send(['article' => $root, 'comments' => $comments]);
    }

    // -------------------------------------------------------------------------
    // GET /api/articles/:nick
    // -------------------------------------------------------------------------

    private function getList(
        int    $profile_uid,
        string $ob_hash,
        string $permission_sql
    ): never {
        $itemspage = intval(get_pconfig(local_channel(), 'system', 'itemspage') ?: 10);
        $offset    = max(0, intval($_GET['start'] ?? 0));
        $pager_sql = " LIMIT $itemspage OFFSET $offset ";

        $search   = $_GET['search'] ?? '';
        $hashtags = $_GET['tag']    ?? '';
        $category = $_GET['cat']    ?? '';

        if ($search && str_starts_with($search, '#')) {
            $hashtags = substr($search, 1);
            $search   = '';
        }

        $sql_extra = '';

        if ($category) {
            $sql_extra .= protect_sprintf(
                term_item_parent_query($profile_uid, 'item', $category, TERM_CATEGORY)
            );
        }
        if ($hashtags) {
            $sql_extra .= protect_sprintf(
                term_query('item', $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG)
            );
        }
        if ($search) {
            $sql_extra .= sprintf(
                " AND (item.body LIKE '%s' OR item.title LIKE '%s') ",
                dbesc(protect_sprintf('%' . $search . '%')),
                dbesc(protect_sprintf('%' . $search . '%'))
            );
        }

        $r = dbq("SELECT item.id AS item_id FROM item
            WHERE item.uid = $profile_uid
            AND item.item_type = " . ITEM_TYPE_ARTICLE . "
            AND item.item_thread_top = 1
            AND item.item_deleted = 0
            AND item.verb != 'Add'
            $permission_sql $sql_extra
            ORDER BY item.created DESC
            $pager_sql");

        $root_count = count($r ?: []);
        $items      = [];

        if ($r) {
            $ids   = ids_to_querystr($r, 'item_id');
            $items = dbq("SELECT item.*, " . $this->reactionSubqueries() . "
                FROM item
                WHERE item.id IN ($ids)
                AND item.item_deleted = 0
                ORDER BY item.created DESC");

            if ($items) {
                xchan_query($items, true);
                $items = fetch_post_tags($items, true);
            }
        }

        $out = [];
        foreach (($items ?: []) as $item) {
            $out[] = $this->formatItem($item, $ob_hash);
        }

        Response::paginate($out, $offset, $itemspage, $root_count);
    }

    // -------------------------------------------------------------------------

    private function reactionSubqueries(): string
    {
        return "
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Like'    AND r.item_deleted = 0) AS like_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Dislike' AND r.item_deleted = 0) AS dislike_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) AS announce_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.id    AND r.item_thread_top = 0    AND r.item_deleted = 0) AS comment_count,
            (SELECT GROUP_CONCAT(verb, ':', author_xchan SEPARATOR '|')
             FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid
               AND r.verb IN ('Like','Dislike','Announce') AND r.item_deleted = 0) AS reaction_verbs";
    }

    private function formatItem(array $item, string $ob_hash): array
    {
        $liked = $disliked = $repeated = false;

        if ($ob_hash && !empty($item['reaction_verbs'])) {
            foreach (explode('|', $item['reaction_verbs']) as $rv) {
                if (!str_contains($rv, ':')) continue;
                [$verb, $xchan] = explode(':', $rv, 2);
                if ($xchan !== $ob_hash) continue;
                if ($verb === 'Like')     $liked    = true;
                if ($verb === 'Dislike')  $disliked = true;
                if ($verb === 'Announce') $repeated = true;
            }
        }

        return [
            'uuid'            => $item['uuid'],
            'mid'             => $item['mid'],
            'parent_mid'      => $item['parent_mid'],
            'thr_parent'      => $item['thr_parent'],
            'created'         => $item['created'],
            'edited'          => $item['edited'],
            'title'           => $item['title'],
            'body'            => $item['body'],
            'verb'            => $item['verb'],
            'obj_type'        => $item['obj_type'],
            'item_type'       => intval($item['item_type']),
            'like_count'      => intval($item['like_count']     ?? 0),
            'dislike_count'   => intval($item['dislike_count']  ?? 0),
            'announce_count'  => intval($item['announce_count'] ?? 0),
            'comment_count'   => intval($item['comment_count']  ?? 0),
            'item_private'    => intval($item['item_private']),
            'item_thread_top' => intval($item['item_thread_top']),
            'iid'             => intval($item['id']),
            'profile_uid'     => intval($item['uid']),
            'flags'           => array_values(array_filter([
                intval($item['item_thread_top']) ? 'thread_parent' : null,
                intval($item['item_private'])    ? 'private'       : null,
                intval($item['item_starred'])    ? 'starred'       : null,
            ])),
            'author'          => [
                'name'    => $item['author']['xchan_name']           ?? '',
                'address' => $item['author']['xchan_addr']           ?? '',
                'url'     => $item['author']['xchan_url']            ?? '',
                'photo'   => [
                    'src'      => $item['author']['xchan_photo_m']        ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'permalink'       => $item['plink'] ?? '',
            'viewer_liked'    => $liked,
            'viewer_disliked' => $disliked,
            'viewer_repeated' => $repeated,
        ];
    }
}
