<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Concerns\ReactionCounts;
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
        $dbegin   = $_GET['dbegin'] ?? '';
        $dend     = $_GET['dend']   ?? '';
        // Activity cursor (stream polling): matches articles bumped by new
        // child activity, not just newly created ones
        $abegin   = $_GET['abegin'] ?? '';

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
        if ($dbegin) {
            $sql_extra .= " AND item.created >= '" . dbesc($dbegin) . "' ";
        }
        if ($dend) {
            $sql_extra .= " AND item.created < '"  . dbesc($dend)   . "' ";
        }
        if ($abegin) {
            $sql_extra .= " AND item.commented >= '" . dbesc($abegin) . "' ";
        }
        // With an activity cursor, order by it too, or bumped old articles
        // sort below every newly created one and can fall off the page
        $order_col = $abegin ? 'commented' : 'created';

        $r = dbq("SELECT item.id AS item_id FROM item
            WHERE item.uid = $profile_uid
            AND item.item_type = " . ITEM_TYPE_ARTICLE . "
            AND item.item_thread_top = 1
            AND item.item_deleted = 0
            AND item.verb != 'Add'
            $permission_sql $sql_extra
            ORDER BY item.$order_col DESC
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
        return ReactionCounts::subqueries();
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
            'summary'         => $item['summary'] ?? '',
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
                'hash'    => $item['author']['xchan_hash']           ?? '',
                'photo'   => [
                    'src'      => $item['author']['xchan_photo_m']        ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'permalink'       => $item['plink'] ?? '',
            'viewer_liked'    => $liked,
            'viewer_disliked' => $disliked,
            'viewer_repeated' => $repeated,
            'can_comment'     => (bool) can_comment_on_post($ob_hash, $item),
            'categories'      => array_values(array_map(
                fn($t) => $t['term'],
                array_filter($item['term'] ?? [], fn($t) => intval($t['ttype']) === TERM_CATEGORY)
            )),
            'tags'            => array_values(array_map(
                fn($t) => $t['term'],
                array_filter($item['term'] ?? [], fn($t) => intval($t['ttype']) === TERM_HASHTAG)
            )),
        ];
    }

    // -------------------------------------------------------------------------
    // POST /api/articles/:nick
    // Body (JSON): { title, summary, body, slug, category, mimetype, post_id? }
    // post_id present → edit existing article via item_store_update
    // post_id absent  → create new article via item_store
    // -------------------------------------------------------------------------

    public function post(): void
    {
        require_once 'include/items.php';
        require_once 'include/security.php';

        $uid = \Theme\Solidified\Api\Auth::requireLocalJson();

        $nick = \App::$argv[2] ?? '';
        if (!$nick) {
            Response::error(400, 'Channel nick required');
        }

        $channel = channelx_by_nick($nick);
        if (!$channel || intval($channel['channel_id']) !== $uid) {
            Response::error(403, 'Permission denied');
        }

        $input    = json_decode(file_get_contents('php://input'), true) ?? [];
        $body     = trim($input['body']     ?? '');
        $title    = escape_tags(trim($input['title']    ?? ''));
        $summary  = escape_tags(trim($input['summary']  ?? ''));
        $slug     = trim($input['slug']     ?? '');
        $category = trim($input['category'] ?? '');
        $mimetype = trim($input['mimetype'] ?? 'text/bbcode');
        $post_id  = intval($input['post_id'] ?? 0);

        if (!$body) {
            Response::error(400, 'Body is required');
        }
        if (!in_array($mimetype, ['text/bbcode', 'text/html', 'text/plain', 'text/markdown'], true)) {
            $mimetype = 'text/bbcode';
        }
        if ($slug) {
            $slug = str_replace('/', '-', strtolower(\URLify::transliterate($slug)));
        }

        // ── Build category term tags ──────────────────────────────────────────
        $post_tags = [];
        if ($category) {
            foreach (explode(',', $category) as $cat) {
                $cat = trim($cat);
                if (!$cat) continue;
                $post_tags[] = [
                    'uid'   => $uid,
                    'ttype' => TERM_CATEGORY,
                    'otype' => TERM_OBJ_POST,
                    'term'  => $cat,
                    'url'   => channel_url($channel) . '?cat=' . urlencode($cat),
                ];
            }
        }

        // ── Edit existing article ─────────────────────────────────────────────
        if ($post_id) {
            $orig = dbq("SELECT * FROM item WHERE id = %d AND uid = %d AND item_type = " . ITEM_TYPE_ARTICLE . " LIMIT 1",
                intval($post_id), $uid);

            if (!$orig) {
                Response::error(404, 'Article not found');
            }

            $datarray                 = $orig[0];
            $datarray['title']        = $title;
            $datarray['summary']      = $summary;
            $datarray['body']         = $body;
            $datarray['mimetype']     = $mimetype;
            $datarray['edited']       = datetime_convert();
            $datarray['changed']      = datetime_convert();
            $datarray['commented']    = datetime_convert();
            $datarray['edit']         = true;
            $datarray['id']           = $post_id;
            $datarray['term']         = $post_tags;

            if ($slug) {
                \Zotlabs\Lib\IConfig::Set($datarray, 'system',
                    item_type_to_namespace(ITEM_TYPE_ARTICLE), $slug, true);
            }

            $result = item_store_update($datarray);

            if (!$result['success']) {
                logger('Articles::post update error: ' . ($result['message'] ?? ''), LOGGER_DEBUG);
                Response::error(500, 'Failed to update article');
            }

            \Zotlabs\Daemon\Master::Summon(['Notifier', 'edit_post', $post_id]);

            Response::send(['uuid' => $orig[0]['uuid'], 'iid' => $post_id]);
        }

        // ── Create new article ────────────────────────────────────────────────
        $uuid = item_message_id();
        $mid  = z_root() . '/item/' . $uuid;
        $now  = datetime_convert();

        $datarray = [
            'aid'             => intval($channel['channel_account_id']),
            'uid'             => $uid,
            'uuid'            => $uuid,
            'mid'             => $mid,
            'parent_mid'      => $mid,
            'thr_parent'      => $mid,
            'owner_xchan'     => $channel['channel_hash'],
            'author_xchan'    => $channel['channel_hash'],
            'created'         => $now,
            'edited'          => $now,
            'commented'       => $now,
            'received'        => $now,
            'changed'         => $now,
            'verb'            => 'Create',
            'obj_type'        => 'Article',
            'item_type'       => ITEM_TYPE_ARTICLE,
            'item_thread_top' => 1,
            'item_origin'     => 1,
            'item_wall'       => 1,
            'item_private'    => 0,
            'mimetype'        => $mimetype,
            'title'           => $title,
            'summary'         => $summary,
            'body'            => $body,
            'allow_cid'       => '',
            'allow_gid'       => '',
            'deny_cid'        => '',
            'deny_gid'        => '',
            'plink'           => $mid,
            'term'            => $post_tags,
        ];

        if ($slug) {
            \Zotlabs\Lib\IConfig::Set($datarray, 'system',
                item_type_to_namespace(ITEM_TYPE_ARTICLE), $slug, true);
        }

        $result = item_store($datarray);

        if (!$result || !$result['success']) {
            logger('Articles::post create error: ' . ($result['message'] ?? ''), LOGGER_DEBUG);
            Response::error(500, 'Failed to create article');
        }

        \Zotlabs\Daemon\Master::Summon(['Notifier', 'wall-new', $result['item_id']]);

        Response::send([
            'uuid' => $result['item']['uuid'] ?? $uuid,
            'iid'  => intval($result['item_id']),
        ], [], 201);
    }



}
