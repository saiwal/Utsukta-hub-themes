<?php
namespace Utsukta\SpaCore\Api\Concerns;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\ContentTypes;
use Utsukta\SpaCore\Api\Response;

/**
 * Shared machinery for the "item collection" apps — Articles and Cards.
 *
 * Both store one item_type in the item table, alias it by a slug in iconfig
 * cat 'system', and group members under a per-app iconfig key (a *series* of
 * articles, a *deck* of cards) with an integer order. Everything below is the
 * part of that which is identical between them; the two handlers keep only
 * their own route dispatch and their own POST body, where the divergence is
 * real (translations and delayed publishing vs. kanban and templates).
 *
 * The host class supplies:
 *   const COLL_ITEM_TYPE     ITEM_TYPE_ARTICLE | ITEM_TYPE_CARD
 *   const COLL_NAME          'article' | 'card'  — iconfig cat + singular response key
 *   const COLL_PLURAL        'articles' | 'cards' — URL segment + plural response key
 *   const COLL_GROUP         'series' | 'deck'    — iconfig key, ?param, action prefix
 *   const COLL_GROUP_PLURAL  'series' | 'decks'   — overview response key
 *
 * and may override formatExtras() / afterSingle().
 */
trait ItemCollection
{
    use EmbedsItems;
    use ResolvesAcl;

    /**
     * Expand the composer's compact embed tokens at save time.
     *
     * Every collection POST needs this, not just the handler that grew it
     * first: an unexpanded [card=<id>] / [share=<id>] is stored verbatim and
     * only ever renders as the fallback chip, and it carries no message_id, so
     * nothing downstream (backlinks included) can see the embed at all.
     *
     * Only bbcode carries these tokens. Non-bbcode bodies need no sanitizing
     * here: this saves through item_store() / item_store_update(), both of
     * which run z_input_filter() on the body themselves (include/items.php:1702,
     * :2192), and filtering again would htmlspecialchars-escape a text/markdown
     * body twice.
     */
    private function expandEmbedTokens(string $mimetype, string $body): string
    {
        if ($mimetype !== 'text/bbcode') {
            return $body;
        }

        return $this->expandCardTags($this->expandShareTags($body));
    }

    /** Extra response keys for one formatted item. */
    private function formatExtras(array $item, array $iconfig): array
    {
        return [];
    }

    /** Decorate a single-item response after the root is formatted. */
    private function afterSingle(array &$root, int $profile_uid, string $permission_sql, string $nick): void
    {
    }

    // -------------------------------------------------------------------------
    // GET preamble — resolve the channel, the observer and the SQL filters.
    // Returns [$channel, $profile_uid, $ob_hash, $permission_sql, $item_normal].
    // -------------------------------------------------------------------------

    private function collectionGetPreamble(): array
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

        // item_normal() excludes hidden/unpublished/pending-remove items and,
        // for non-owners, blocked/delayed ones too. The root-item queries did
        // not check this on their own, so a scheduled or moderation-pending
        // item (and its categories) leaked to any ACL-permitted visitor even
        // though the categories widget (which does call item_normal()) hid it,
        // producing a card/widget mismatch. Callers apply $item_normal only to
        // root-items-only queries — the thread query in getSingle() also pulls
        // in comments, which are always item_type = ITEM_TYPE_POST regardless
        // of the parent's type, so item_normal()'s item_type match would
        // wrongly drop every reply.
        return [
            $channel,
            $profile_uid,
            $ob_hash,
            item_permissions_sql($profile_uid),
            item_normal($profile_uid, 'item', static::COLL_ITEM_TYPE),
        ];
    }

    // -------------------------------------------------------------------------
    // GET /spa/<plural>/:nick/<group>            -> groups with counts
    // GET /spa/<plural>/:nick/<group>/:name      -> ordered member items
    // -------------------------------------------------------------------------

    private function getGroupOverview(int $profile_uid, string $permission_sql): never
    {
        $cat   = static::COLL_NAME;
        $key   = static::COLL_GROUP;
        $order = $key . '_order';

        $rows = dbq("SELECT s.v AS name, COUNT(*) AS total,
                MIN(CAST(o.v AS UNSIGNED)) AS min_order,
                MAX(CAST(o.v AS UNSIGNED)) AS max_order
            FROM iconfig s
            INNER JOIN item ON item.id = s.iid
            LEFT JOIN iconfig o ON o.iid = s.iid AND o.cat = '$cat' AND o.k = '$order'
            WHERE s.cat = '$cat' AND s.k = '$key'
            AND item.uid = $profile_uid
            AND item.item_type = " . static::COLL_ITEM_TYPE . "
            AND item.item_deleted = 0
            $permission_sql
            GROUP BY s.v
            ORDER BY s.v ASC");

        $groups = array_map(fn($r) => [
            'name'      => $r['name'],
            'count'     => intval($r['total']),
            'min_order' => $r['min_order'] !== null ? intval($r['min_order']) : null,
            'max_order' => $r['max_order'] !== null ? intval($r['max_order']) : null,
        ], $rows ?: []);

        Response::send([static::COLL_GROUP_PLURAL => $groups]);
    }

    private function getGroupDetail(
        string $name,
        int    $profile_uid,
        string $ob_hash,
        string $permission_sql,
        string $nick
    ): never {
        $cat       = static::COLL_NAME;
        $key       = static::COLL_GROUP;
        $order     = $key . '_order';
        $name_safe = dbesc($name);

        $r = dbq("SELECT item.id AS item_id FROM item
            INNER JOIN iconfig s ON s.iid = item.id AND s.cat = '$cat' AND s.k = '$key' AND s.v = '$name_safe'
            LEFT JOIN iconfig o ON o.iid = item.id AND o.cat = '$cat' AND o.k = '$order'
            WHERE item.uid = $profile_uid
            AND item.item_type = " . static::COLL_ITEM_TYPE . "
            AND item.item_deleted = 0
            AND item.item_thread_top = 1
            AND item.verb != 'Add'
            $permission_sql
            ORDER BY CAST(o.v AS UNSIGNED) ASC, item.created ASC");

        // The id-list query below has no ORDER BY of its own, so the group
        // order computed above is re-applied by walking $r.
        Response::send([
            'name'               => $name,
            static::COLL_PLURAL  => $this->hydrateInIdOrder($r ?: [], $ob_hash, $nick),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /spa/<plural>/:nick/:uuid-or-slug
    // -------------------------------------------------------------------------

    private function getSingle(
        string $identifier,
        int    $profile_uid,
        string $ob_hash,
        string $permission_sql,
        string $nick
    ): never {
        $identifier_safe = dbesc($identifier);
        $label           = ucfirst(static::COLL_NAME);

        $r = dbq("SELECT id FROM item
            WHERE item.uid = $profile_uid
            AND item.uuid = '$identifier_safe'
            AND item.item_type = " . static::COLL_ITEM_TYPE . "
            AND item.item_deleted = 0
            LIMIT 1");

        if (!$r) {
            // Not a uuid match — try resolving it as a slug (iconfig alias).
            $r = dbq("SELECT item.id FROM item
                LEFT JOIN iconfig ON iconfig.iid = item.id
                WHERE item.uid = $profile_uid
                AND iconfig.cat = 'system'
                AND iconfig.k = '" . item_type_to_namespace(static::COLL_ITEM_TYPE) . "'
                AND iconfig.v = '$identifier_safe'
                AND item.item_type = " . static::COLL_ITEM_TYPE . "
                AND item.item_deleted = 0
                LIMIT 1");
        }

        if (!$r) {
            Response::error(404, $label . ' not found');
        }

        $iid = intval($r[0]['id']);

        $items = dbq("SELECT item.*, " . ReactionCounts::subqueries() . "
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
                $root = $this->formatCollectionItem($item, $ob_hash, $nick);
            } else {
                $comments[] = $this->formatCollectionItem($item, $ob_hash, $nick);
            }
        }

        if (!$root) {
            Response::error(404, 'Root item not found');
        }

        $this->afterSingle($root, $profile_uid, $permission_sql, $nick);

        Response::send([static::COLL_NAME => $root, 'comments' => $comments]);
    }

    // -------------------------------------------------------------------------
    // GET /spa/<plural>/:nick
    // -------------------------------------------------------------------------

    private function getList(
        int    $profile_uid,
        string $ob_hash,
        string $permission_sql,
        string $nick
    ): never {
        $itemspage = max(1, min(30, intval(get_pconfig(local_channel(), 'system', 'itemspage') ?: 10)));
        $offset    = max(0, intval($_GET['start'] ?? 0));
        $pager_sql = " LIMIT $itemspage OFFSET $offset ";

        $search   = $_GET['search'] ?? '';
        $hashtags = $_GET['tag']    ?? '';
        $category = $_GET['cat']    ?? '';
        $dbegin   = $_GET['dbegin'] ?? '';
        $dend     = $_GET['dend']   ?? '';
        $group    = $_GET[static::COLL_GROUP] ?? '';

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

        $group_join = '';
        $order_by   = 'item.created DESC';
        if ($group) {
            $cat        = static::COLL_NAME;
            $key        = static::COLL_GROUP;
            $group_safe = dbesc($group);
            $group_join = " INNER JOIN iconfig sflt ON sflt.iid = item.id
                AND sflt.cat = '$cat' AND sflt.k = '$key' AND sflt.v = '$group_safe'
                LEFT JOIN iconfig oflt ON oflt.iid = item.id
                AND oflt.cat = '$cat' AND oflt.k = '{$key}_order' ";
            $order_by   = 'CAST(oflt.v AS UNSIGNED) ASC, item.created ASC';
        }

        $r = dbq("SELECT item.id AS item_id FROM item
            $group_join
            WHERE item.uid = $profile_uid
            AND item.item_type = " . static::COLL_ITEM_TYPE . "
            AND item.item_thread_top = 1
            AND item.item_deleted = 0
            AND item.verb != 'Add'
            $permission_sql $sql_extra
            ORDER BY $order_by
            $pager_sql");

        $r = $r ?: [];

        Response::paginate(
            $this->hydrateInIdOrder($r, $ob_hash, $nick),
            $offset,
            $itemspage,
            count($r)
        );
    }

    /**
     * Load the full rows for an id list and format them in exactly that order.
     * "WHERE id IN (...)" does not guarantee row order, and the group ordering
     * in particular can't be re-derived from item.created alone.
     *
     * @param array $rows rows carrying an 'item_id' column, in the wanted order
     */
    private function hydrateInIdOrder(array $rows, string $ob_hash, string $nick): array
    {
        if (!$rows) {
            return [];
        }

        $ids   = ids_to_querystr($rows, 'item_id');
        $items = dbq("SELECT item.*, " . ReactionCounts::subqueries() . "
            FROM item
            WHERE item.id IN ($ids)
            AND item.item_deleted = 0");

        if (!$items) {
            return [];
        }

        xchan_query($items, true);
        $items = fetch_post_tags($items, true);

        $byId = [];
        foreach ($items as $item) {
            $byId[intval($item['id'])] = $item;
        }

        $out = [];
        foreach ($rows as $row) {
            $iid = intval($row['item_id']);
            if (isset($byId[$iid])) {
                $out[] = $this->formatCollectionItem($byId[$iid], $ob_hash, $nick);
            }
        }
        return $out;
    }

    // -------------------------------------------------------------------------

    private function formatCollectionItem(array $item, string $ob_hash, string $nick): array
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

        // Flatten iconfig (attached by fetch_post_tags / xchan_query) to
        // "<cat>/<k>" => v so the slug, the group metadata and whatever
        // formatExtras() wants are all read in one pass.
        $iconfig = [];
        foreach ((array) ($item['iconfig'] ?? []) as $cfg) {
            $iconfig[($cfg['cat'] ?? '') . '/' . ($cfg['k'] ?? '')] = $cfg['v'];
        }

        $slug = isset($iconfig['system/' . item_type_to_namespace(static::COLL_ITEM_TYPE)])
            ? urldecode($iconfig['system/' . item_type_to_namespace(static::COLL_ITEM_TYPE)])
            : '';

        $groupName  = $iconfig[static::COLL_NAME . '/' . static::COLL_GROUP] ?? null;
        $groupOrder = $iconfig[static::COLL_NAME . '/' . static::COLL_GROUP . '_order'] ?? null;
        $group      = $groupName !== null
            ? ['name' => $groupName, 'order' => $groupOrder !== null ? intval($groupOrder) : null]
            : null;

        $attachRaw = $item['attach'] ?? '';
        $root = z_root();
        $attach = array_map(function (array $a) use ($root): array {
            if (!isset($a['href']) && isset($a['url'])) {
                $a['href'] = $a['url'];
            }
            if (isset($a['href']) && str_starts_with($a['href'], '/')) {
                $a['href'] = $root . $a['href'];
            }
            return $a;
        }, $attachRaw ? (json_decode($attachRaw, true) ?: []) : []);

        return [
            'uuid'            => $item['uuid'],
            'mid'             => $item['mid'],
            'parent_mid'      => $item['parent_mid'],
            'thr_parent'      => $item['thr_parent'],
            'created'         => $item['created'],
            'edited'          => $item['edited'],
            static::COLL_GROUP => $group,
            'title'           => Response::decodeEntities($item['title']),
            'body'            => ContentTypes::decode($item['body'], $item['mimetype'] ?? ''),
            'mimetype'        => $item['mimetype'] ?? '',
            'summary'         => Response::decodeEntities($item['summary'] ?? ''),
            'slug'            => $slug,
            // Human-facing app URL (slug when set, uuid otherwise) — distinct
            // from 'permalink' (the immutable mid-based federation identity).
            'view_url'        => z_root() . '/' . static::COLL_PLURAL . '/' . $nick . '/' . ($slug ?: $item['uuid']),
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
                'name'    => Response::decodeEntities($item['author']['xchan_name']  ?? ''),
                'address' => $item['author']['xchan_addr']           ?? '',
                'url'     => $item['author']['xchan_url']            ?? '',
                'hash'    => $item['author']['xchan_hash']           ?? '',
                'photo'   => [
                    'src'      => $item['author']['xchan_photo_m']        ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'permalink'       => $item['plink'] ?? '',
            'public_policy'   => $item['public_policy'] ?? '',
            'allow_cid'       => self::parseHashList($item['allow_cid'] ?? ''),
            'allow_gid'       => self::parseHashList($item['allow_gid'] ?? ''),
            'deny_cid'        => self::parseHashList($item['deny_cid']  ?? ''),
            'deny_gid'        => self::parseHashList($item['deny_gid']  ?? ''),
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
            'attach'          => $attach,
        ] + $this->formatExtras($item, $iconfig);
    }

    // -------------------------------------------------------------------------
    // POST helpers
    // -------------------------------------------------------------------------

    /**
     * Authenticate, resolve the channel from :nick and confirm the caller owns
     * it. Returns [$uid, $nick, $channel, $input].
     */
    private function collectionPostPreamble(): array
    {
        require_once 'include/items.php';
        require_once 'include/security.php';

        $uid = Auth::requireLocalJson();

        $nick = \App::$argv[2] ?? '';
        if (!$nick) {
            Response::error(400, 'Channel nick required');
        }

        $channel = channelx_by_nick($nick);
        if (!$channel || intval($channel['channel_id']) !== $uid) {
            Response::error(403, 'Permission denied');
        }

        // Auth::requireLocalJson() already parsed the JSON body — re-reading
        // php://input here would return empty, since the stream is drained.
        return [$uid, $nick, $channel, Auth::$parsedBody];
    }

    /**
     * Pull [attachment] tags out of $body into an attach array (and strip them
     * from the body). Mirrors Item.php's create/comment handlers so uploaded
     * files show up as native attachments — with a working preview/player —
     * instead of raw bbcode text in the rendered item.
     *
     * @param array $acl the 6-tuple from aclFromComposerInput()
     */
    private function extractAttachments(int $uid, array $channel, string $mimetype, string &$body, array $acl): array
    {
        [$allow_cid, $allow_gid, $deny_cid, $deny_gid] = $acl;

        $attachments = [];
        if ($mimetype === 'text/bbcode' && preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/', $body, $match)) {
            require_once 'include/attach.php';
            fix_attached_permissions($uid, $body, $allow_cid, $allow_gid, $deny_cid, $deny_gid);
            foreach ($match[2] as $i => $mtch) {
                $hash = substr($mtch, 0, strpos($mtch, ','));
                $rev  = intval(substr($mtch, strpos($mtch, ',')));
                $r    = attach_by_hash_nodata($hash, $channel['channel_hash'], $rev);
                if ($r['success']) {
                    $attachments[] = [
                        'href'     => z_root() . '/attach/' . $r['data']['hash'],
                        'length'   => $r['data']['filesize'],
                        'type'     => $r['data']['filetype'],
                        'title'    => urlencode($r['data']['filename']),
                        'revision' => $r['data']['revision'],
                    ];
                }
                $body = str_replace($match[1][$i], '', $body);
            }
        }
        return $attachments;
    }

    /** Comma-separated categories -> TERM_CATEGORY term rows. */
    private function categoryTerms(int $uid, array $channel, string $category): array
    {
        $post_tags = [];
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
        return $post_tags;
    }

    /**
     * Load an existing item for editing and apply everything that is the same
     * for both apps: the content fields, the ACL, the preserved term rows and
     * the preserved iconfig rows. The caller then sets its own iconfig keys and
     * calls item_store_update().
     *
     * @param array $fields    title, summary, body, mimetype
     * @param bool  $catsGiven whether the request carried a 'category' key at all
     * @param array $acl       the 6-tuple from aclFromComposerInput()
     */
    private function buildEditDatarray(int $post_id, int $uid, array $fields, array $post_tags, bool $catsGiven, array $acl): array
    {
        // dbq() runs the SQL string as-is — it does not do q()'s printf-style
        // placeholder substitution — so values must be interpolated here.
        $orig = dbq("SELECT * FROM item WHERE id = " . intval($post_id) . "
            AND uid = " . intval($uid) . "
            AND item_type = " . static::COLL_ITEM_TYPE . " LIMIT 1");

        if (!$orig) {
            Response::error(404, ucfirst(static::COLL_NAME) . ' not found');
        }

        [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy] = $acl;

        $datarray              = $orig[0];
        $datarray['title']     = $fields['title'];
        $datarray['summary']   = $fields['summary'];
        $datarray['body']      = $fields['body'];
        $datarray['mimetype']  = $fields['mimetype'];
        $datarray['edited']    = datetime_convert();
        $datarray['changed']   = datetime_convert();
        $datarray['commented'] = datetime_convert();
        $datarray['edit']      = true;
        $datarray['id']        = $post_id;

        // item_store_update() rebuilds terms from $datarray['term'] behind an
        // unconditional "delete from term where oid = ... and otype = ..." that
        // is NOT filtered by ttype. $post_tags only ever holds categories, so
        // assigning it raw destroyed the item's hashtags and mentions too —
        // and, whenever the client sent category='', its categories as well.
        // Same hazard the iconfig pre-load below already guards against.
        $existingTerms = dbq("SELECT * FROM term WHERE oid = " . intval($post_id)
            . " AND otype = " . intval(TERM_OBJ_POST)) ?: [];
        $keptTerms = array_values(array_filter(
            $existingTerms,
            fn($t) => intval($t['ttype']) !== TERM_CATEGORY
        ));
        $datarray['term'] = $catsGiven
            ? array_merge($keptTerms, $post_tags)
            : $existingTerms;

        $datarray['allow_cid']     = $allow_cid;
        $datarray['allow_gid']     = $allow_gid;
        $datarray['deny_cid']      = $deny_cid;
        $datarray['deny_gid']      = $deny_gid;
        $datarray['item_private']  = $item_private;
        $datarray['public_policy'] = $public_policy;

        // Pre-load existing iconfig rows — item_store_update() deletes ALL of
        // an item's iconfig rows and re-inserts only what's in
        // $datarray['iconfig'], so setting/clearing just one key here without
        // first loading the others would silently drop them (e.g. the slug, or
        // the group metadata, when only the title changes).
        $datarray['iconfig'] = dbq("SELECT * FROM iconfig WHERE iid = " . intval($post_id)) ?: [];

        // Re-index the body's embeds (Cards' "Mentioned in" reads these).
        // After the pre-load, so it replaces the stored row rather than
        // being replaced by it.
        $this->setEmbedIconfig($datarray, $fields['body']);

        return $datarray;
    }

    /**
     * Set (or clear) the group name + order on a datarray or item id.
     *
     * $target is by reference because IConfig::Set()/Delete() take it that
     * way: given a datarray they append to its 'iconfig' key for item_store()
     * to write, and by value the caller would keep an unmodified copy and
     * store an item with no slug and no group at all.
     */
    private function setGroupIconfig(&$target, string $group, ?int $order): void
    {
        $cat = static::COLL_NAME;
        $key = static::COLL_GROUP;

        if ($group) {
            \Zotlabs\Lib\IConfig::Set($target, $cat, $key, $group);
            \Zotlabs\Lib\IConfig::Set($target, $cat, $key . '_order', $order ?? 0);
        } else {
            \Zotlabs\Lib\IConfig::Delete($target, $cat, $key);
            \Zotlabs\Lib\IConfig::Delete($target, $cat, $key . '_order');
        }
    }

    /** Set the item's slug alias (iconfig cat 'system'). By reference: see setGroupIconfig(). */
    private function setSlugIconfig(&$target, string $slug): void
    {
        \Zotlabs\Lib\IConfig::Set($target, 'system',
            item_type_to_namespace(static::COLL_ITEM_TYPE), $slug, true);
    }

    // -------------------------------------------------------------------------
    // POST /spa/<plural>/:nick/<group>-rename   { from, to }
    // POST /spa/<plural>/:nick/<group>-reorder  { <group>, order: [uuid, ...] }
    // Owner-only management actions on group metadata, applied as direct
    // iconfig writes — no item content changes, so item_store*() isn't involved.
    // -------------------------------------------------------------------------

    private function renameGroup(int $uid, array $input): never
    {
        $from = trim($input['from'] ?? '');
        $to   = trim($input['to']   ?? '');

        if (!$from || !$to) {
            Response::error(400, 'from and to are required');
        }

        q("UPDATE iconfig SET v = '%s'
            WHERE cat = '%s' AND k = '%s' AND v = '%s'
            AND iid IN (SELECT id FROM item WHERE uid = %d AND item_type = %d)",
            dbesc($to), dbesc(static::COLL_NAME), dbesc(static::COLL_GROUP),
            dbesc($from), intval($uid), intval(static::COLL_ITEM_TYPE)
        );

        Response::send(['name' => $to]);
    }

    private function reorderGroup(int $uid, array $input): never
    {
        $group = trim($input[static::COLL_GROUP] ?? '');
        $order = (array) ($input['order'] ?? []);

        if (!$group || !$order) {
            Response::error(400, static::COLL_GROUP . ' and order are required');
        }

        foreach (array_values($order) as $index => $memberUuid) {
            $r = dbq("SELECT id FROM item
                WHERE uid = " . intval($uid) . "
                AND uuid = '" . dbesc((string) $memberUuid) . "'
                AND item_type = " . static::COLL_ITEM_TYPE . " LIMIT 1");
            if ($r) {
                $memberId = intval($r[0]['id']);
                \Zotlabs\Lib\IConfig::Set($memberId,
                    static::COLL_NAME, static::COLL_GROUP . '_order', $index + 1);
            }
        }

        Response::send([static::COLL_GROUP => $group, 'order' => array_values($order)]);
    }
}
