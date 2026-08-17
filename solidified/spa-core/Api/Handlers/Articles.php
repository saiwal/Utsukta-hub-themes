<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Concerns\ReactionCounts;
use Utsukta\SpaCore\Api\Response;

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

        $sub = \App::$argv[3] ?? '';
        if ($sub === 'series') {
            $seriesName = \App::$argv[4] ?? '';
            if ($seriesName) {
                $this->getSeriesDetail($seriesName, $profile_uid, $ob_hash, $permission_sql, $nick);
            }
            $this->getSeriesOverview($profile_uid, $permission_sql);
        }

        $identifier = $sub ?: ($_GET['uuid'] ?? '');
        if ($identifier) {
            $this->getSingle($identifier, $profile_uid, $ob_hash, $permission_sql, $nick);
        }

        $this->getList($profile_uid, $ob_hash, $permission_sql, $nick);
    }

    // -------------------------------------------------------------------------
    // GET /api/articles/:nick/series            -> list of series with counts
    // GET /api/articles/:nick/series/:name      -> ordered member articles
    // -------------------------------------------------------------------------

    private function getSeriesOverview(int $profile_uid, string $permission_sql): never
    {
        $rows = dbq("SELECT s.v AS name, COUNT(*) AS total,
                MIN(CAST(o.v AS UNSIGNED)) AS min_order,
                MAX(CAST(o.v AS UNSIGNED)) AS max_order
            FROM iconfig s
            INNER JOIN item ON item.id = s.iid
            LEFT JOIN iconfig o ON o.iid = s.iid AND o.cat = 'article' AND o.k = 'series_order'
            WHERE s.cat = 'article' AND s.k = 'series'
            AND item.uid = $profile_uid
            AND item.item_type = " . ITEM_TYPE_ARTICLE . "
            AND item.item_deleted = 0
            $permission_sql
            GROUP BY s.v
            ORDER BY s.v ASC");

        $series = array_map(fn($r) => [
            'name'      => $r['name'],
            'count'     => intval($r['total']),
            'min_order' => $r['min_order'] !== null ? intval($r['min_order']) : null,
            'max_order' => $r['max_order'] !== null ? intval($r['max_order']) : null,
        ], $rows ?: []);

        Response::send(['series' => $series]);
    }

    private function getSeriesDetail(
        string $name,
        int    $profile_uid,
        string $ob_hash,
        string $permission_sql,
        string $nick
    ): never {
        $name_safe = dbesc($name);

        $r = dbq("SELECT item.id AS item_id FROM item
            INNER JOIN iconfig s ON s.iid = item.id AND s.cat = 'article' AND s.k = 'series' AND s.v = '$name_safe'
            LEFT JOIN iconfig o ON o.iid = item.id AND o.cat = 'article' AND o.k = 'series_order'
            WHERE item.uid = $profile_uid
            AND item.item_type = " . ITEM_TYPE_ARTICLE . "
            AND item.item_deleted = 0
            AND item.item_thread_top = 1
            AND item.verb != 'Add'
            $permission_sql
            ORDER BY CAST(o.v AS UNSIGNED) ASC, item.created ASC");

        $out = [];

        if ($r) {
            $ids   = ids_to_querystr($r, 'item_id');
            $items = dbq("SELECT item.*, " . $this->reactionSubqueries() . "
                FROM item
                WHERE item.id IN ($ids)
                AND item.item_deleted = 0");

            if ($items) {
                xchan_query($items, true);
                $items = fetch_post_tags($items, true);

                // Preserve the series_order sort computed above — the id-list
                // query above has no ORDER BY of its own.
                $byId = [];
                foreach ($items as $item) {
                    $byId[intval($item['id'])] = $item;
                }
                foreach ($r as $row) {
                    $iid = intval($row['item_id']);
                    if (isset($byId[$iid])) {
                        $out[] = $this->formatItem($byId[$iid], $ob_hash, $nick);
                    }
                }
            }
        }

        Response::send(['name' => $name, 'articles' => $out]);
    }

    // -------------------------------------------------------------------------
    // GET /api/articles/:nick/:uuid-or-slug
    // -------------------------------------------------------------------------

    private function getSingle(
        string $identifier,
        int    $profile_uid,
        string $ob_hash,
        string $permission_sql,
        string $nick
    ): never {
        $identifier_safe = dbesc($identifier);

        $r = dbq("SELECT id FROM item
            WHERE item.uid = $profile_uid
            AND item.uuid = '$identifier_safe'
            AND item.item_type = " . ITEM_TYPE_ARTICLE . "
            AND item.item_deleted = 0
            LIMIT 1");

        if (!$r) {
            // Not a uuid match — try resolving it as a slug (iconfig alias).
            $r = dbq("SELECT item.id FROM item
                LEFT JOIN iconfig ON iconfig.iid = item.id
                WHERE item.uid = $profile_uid
                AND iconfig.cat = 'system'
                AND iconfig.k = '" . item_type_to_namespace(ITEM_TYPE_ARTICLE) . "'
                AND iconfig.v = '$identifier_safe'
                AND item.item_type = " . ITEM_TYPE_ARTICLE . "
                AND item.item_deleted = 0
                LIMIT 1");
        }

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
                $root = $this->formatItem($item, $ob_hash, $nick);
            } else {
                $comments[] = $this->formatItem($item, $ob_hash, $nick);
            }
        }

        if (!$root) {
            Response::error(404, 'Root item not found');
        }

        if (!empty($root['translation_group'])) {
            $group_safe = dbesc($root['translation_group']);
            $siblings = dbq("SELECT item.uuid, item.title, item.lang,
                    (SELECT v FROM iconfig sl WHERE sl.iid = item.id AND sl.cat = 'system'
                        AND sl.k = '" . item_type_to_namespace(ITEM_TYPE_ARTICLE) . "' LIMIT 1) AS slug
                FROM item
                INNER JOIN iconfig tg ON tg.iid = item.id AND tg.cat = 'article'
                    AND tg.k = 'translation_group' AND tg.v = '$group_safe'
                WHERE item.uid = $profile_uid
                AND item.uuid != '" . dbesc($root['uuid']) . "'
                AND item.item_deleted = 0
                $permission_sql");

            $root['translations'] = array_map(fn($s) => [
                'uuid'     => $s['uuid'],
                'lang'     => $s['lang'],
                'title'    => Response::decodeEntities($s['title']),
                'view_url' => z_root() . '/articles/' . $nick . '/' . ($s['slug'] ? urldecode($s['slug']) : $s['uuid']),
            ], $siblings ?: []);
        } else {
            $root['translations'] = [];
        }

        Response::send(['article' => $root, 'comments' => $comments]);
    }

    // -------------------------------------------------------------------------
    // GET /api/articles/:nick
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
        $series   = $_GET['series'] ?? '';

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

        $series_join = '';
        $order_by    = 'item.created DESC';
        if ($series) {
            $series_safe  = dbesc($series);
            $series_join  = " INNER JOIN iconfig sflt ON sflt.iid = item.id
                AND sflt.cat = 'article' AND sflt.k = 'series' AND sflt.v = '$series_safe'
                LEFT JOIN iconfig oflt ON oflt.iid = item.id
                AND oflt.cat = 'article' AND oflt.k = 'series_order' ";
            $order_by     = 'CAST(oflt.v AS UNSIGNED) ASC, item.created ASC';
        }

        $r = dbq("SELECT item.id AS item_id FROM item
            $series_join
            WHERE item.uid = $profile_uid
            AND item.item_type = " . ITEM_TYPE_ARTICLE . "
            AND item.item_thread_top = 1
            AND item.item_deleted = 0
            AND item.verb != 'Add'
            $permission_sql $sql_extra
            ORDER BY $order_by
            $pager_sql");

        $root_count = count($r ?: []);
        $items      = [];

        if ($r) {
            $ids   = ids_to_querystr($r, 'item_id');
            $items = dbq("SELECT item.*, " . $this->reactionSubqueries() . "
                FROM item
                WHERE item.id IN ($ids)
                AND item.item_deleted = 0");

            if ($items) {
                xchan_query($items, true);
                $items = fetch_post_tags($items, true);
            }
        }

        // Re-apply the id order chosen by the first (joined/paginated) query —
        // "WHERE id IN (...)" does not guarantee row order, and the series
        // ordering in particular can't be re-derived from item.created alone.
        $byId = [];
        foreach (($items ?: []) as $item) {
            $byId[intval($item['id'])] = $item;
        }

        $out = [];
        foreach (($r ?: []) as $row) {
            $iid = intval($row['item_id']);
            if (isset($byId[$iid])) {
                $out[] = $this->formatItem($byId[$iid], $ob_hash, $nick);
            }
        }

        Response::paginate($out, $offset, $itemspage, $root_count);
    }

    // -------------------------------------------------------------------------

    private function reactionSubqueries(): string
    {
        return ReactionCounts::subqueries();
    }

    private function formatItem(array $item, string $ob_hash, string $nick): array
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

        // Extract the ARTICLE slug + series/translation-group metadata from
        // iconfig (attached by fetch_post_tags / xchan_query) in one pass.
        $slug              = '';
        $seriesName        = null;
        $seriesOrder       = null;
        $translationGroup  = null;
        if (!empty($item['iconfig']) && is_array($item['iconfig'])) {
            foreach ($item['iconfig'] as $cfg) {
                $cat = $cfg['cat'] ?? '';
                $k   = $cfg['k']   ?? '';
                if ($cat === 'system' && $k === item_type_to_namespace(ITEM_TYPE_ARTICLE)) {
                    $slug = urldecode($cfg['v']);
                } elseif ($cat === 'article' && $k === 'series') {
                    $seriesName = $cfg['v'];
                } elseif ($cat === 'article' && $k === 'series_order') {
                    $seriesOrder = intval($cfg['v']);
                } elseif ($cat === 'article' && $k === 'translation_group') {
                    $translationGroup = $cfg['v'];
                }
            }
        }
        $series = $seriesName !== null ? ['name' => $seriesName, 'order' => $seriesOrder] : null;

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
            'lang'            => $item['lang'] ?? '',
            'series'          => $series,
            'translation_group' => $translationGroup,
            'title'           => Response::decodeEntities($item['title']),
            'body'            => $item['body'],
            'summary'         => Response::decodeEntities($item['summary'] ?? ''),
            'slug'            => $slug,
            // Human-facing app URL (slug when set, uuid otherwise) — distinct
            // from 'permalink' (the immutable mid-based federation identity).
            'view_url'        => z_root() . '/articles/' . $nick . '/' . ($slug ?: $item['uuid']),
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
        ];
    }

    // Hubzilla stores ACL as "<hash1><hash2>..." — extract the bare hashes.
    private static function parseHashList(string $str): array
    {
        if (!$str) return [];
        preg_match_all('/<([^>]+)>/', $str, $m);
        return $m[1] ?? [];
    }

    // Resolve the raw contact_allow/group_allow/contact_deny/group_deny arrays
    // + public_policy sent by ArticleComposer into item ACL columns.
    private function resolveAcl(array $input, string $ownerHash): array
    {
        if (($input['scope'] ?? null) === 'private') {
            return ['<' . $ownerHash . '>', '', '', '', 1, ''];
        }

        $wrap = fn(array $arr): string =>
            implode('', array_map(fn($h) => '<' . $h . '>', array_filter($arr)));

        $allow_cid     = $wrap((array) ($input['contact_allow'] ?? []));
        $allow_gid     = $wrap((array) ($input['group_allow']   ?? []));
        $deny_cid      = $wrap((array) ($input['contact_deny']  ?? []));
        $deny_gid      = $wrap((array) ($input['group_deny']    ?? []));
        $public_policy = trim($input['public_policy'] ?? '');

        $item_private = ($public_policy === 'contacts' || $allow_cid || $allow_gid) ? 1 : 0;

        return [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy];
    }

    // -------------------------------------------------------------------------
    // POST /api/articles/:nick
    // Body (JSON): { title, summary, body, slug, category, mimetype, lang, post_id?,
    //                series?, series_order?, translation_of?,
    //                contact_allow?, group_allow?, contact_deny?, group_deny?, public_policy? }
    // post_id present → edit existing article via item_store_update
    // post_id absent  → create new article via item_store
    // translation_of (create-only) → link the new article to the source article's
    // translation group (see renameSeries()/reorderSeries() below for the
    // separate series management actions dispatched via argv[3]).
    // -------------------------------------------------------------------------

    public function post(): void
    {
        require_once 'include/items.php';
        require_once 'include/security.php';

        $uid = \Utsukta\SpaCore\Api\Auth::requireLocalJson();

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
        $input = \Utsukta\SpaCore\Api\Auth::$parsedBody;

        $action = \App::$argv[3] ?? '';
        if ($action === 'series-rename') {
            $this->renameSeries($uid, $input);
        }
        if ($action === 'series-reorder') {
            $this->reorderSeries($uid, $input);
        }

        $body     = trim($input['body']     ?? '');
        $title    = escape_tags(trim($input['title']    ?? ''));
        $summary  = escape_tags(trim($input['summary']  ?? ''));
        $slug     = trim($input['slug']     ?? '');
        $category  = trim($input['category'] ?? '');
        // Presence is authoritative (same convention as Item.php's editItem):
        // key absent = keep the article's stored categories, '' = clear them.
        $catsGiven = array_key_exists('category', $input);
        $mimetype = trim($input['mimetype'] ?? 'text/bbcode');
        $post_id  = intval($input['post_id'] ?? 0);
        $lang     = trim($input['lang']     ?? '');
        $series   = trim($input['series']   ?? '');
        $seriesOrder    = isset($input['series_order']) ? intval($input['series_order']) : null;
        $translationOf  = trim($input['translation_of'] ?? '');

        if (!$body) {
            Response::error(400, 'Body is required');
        }
        if (!$lang) {
            Response::error(400, 'Language is required');
        }
        if (!in_array($mimetype, ['text/bbcode', 'text/html', 'text/plain', 'text/markdown'], true)) {
            $mimetype = 'text/bbcode';
        }
        if ($slug) {
            $slug = str_replace('/', '-', strtolower(\URLify::transliterate($slug)));
        }

        // ── Resolve ACL (same for create and edit) ──────────────────────────────
        [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy] =
            $this->resolveAcl($input, $channel['channel_hash']);

        // ── Extract [attachment] tags → attach array, strip them from body ──────
        // Mirrors Item.php's create/comment handlers so uploaded files show up
        // as native attachments (with a working preview/player) instead of raw
        // bbcode text in the rendered article.
        $attachments = [];
        if ($mimetype === 'text/bbcode' && preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/', $body, $match)) {
            require_once('include/attach.php');
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
            // dbq() runs the SQL string as-is — it does not do q()'s printf-style
            // placeholder substitution — so values must be interpolated here.
            $orig = dbq("SELECT * FROM item WHERE id = " . intval($post_id) . "
                AND uid = " . intval($uid) . "
                AND item_type = " . ITEM_TYPE_ARTICLE . " LIMIT 1");

            if (!$orig) {
                Response::error(404, 'Article not found');
            }

            $datarray                   = $orig[0];
            $datarray['title']          = $title;
            $datarray['summary']        = $summary;
            $datarray['body']           = $body;
            $datarray['mimetype']       = $mimetype;
            $datarray['edited']         = datetime_convert();
            $datarray['changed']        = datetime_convert();
            $datarray['commented']      = datetime_convert();
            $datarray['edit']           = true;
            $datarray['id']             = $post_id;

            // item_store_update() rebuilds terms from $datarray['term'] behind an
            // unconditional "delete from term where oid = ... and otype = ..." that
            // is NOT filtered by ttype. $post_tags only ever holds categories, so
            // assigning it raw destroyed the article's hashtags and mentions too —
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
            $datarray['attach']         = $attachments;
            $datarray['allow_cid']      = $allow_cid;
            $datarray['allow_gid']      = $allow_gid;
            $datarray['deny_cid']       = $deny_cid;
            $datarray['deny_gid']       = $deny_gid;
            $datarray['item_private']   = $item_private;
            $datarray['public_policy']  = $public_policy;

            // Pre-load existing iconfig rows — item_store_update() deletes
            // ALL of an item's iconfig rows and re-inserts only what's in
            // $datarray['iconfig'], so setting/clearing just one key here
            // without first loading the others would silently drop them
            // (e.g. the slug, or series metadata, when only lang changes).
            $datarray['iconfig'] = dbq("SELECT * FROM iconfig WHERE iid = " . intval($post_id)) ?: [];

            if ($slug) {
                \Zotlabs\Lib\IConfig::Set($datarray, 'system',
                    item_type_to_namespace(ITEM_TYPE_ARTICLE), $slug, true);
            }
            if ($series) {
                \Zotlabs\Lib\IConfig::Set($datarray, 'article', 'series', $series);
                \Zotlabs\Lib\IConfig::Set($datarray, 'article', 'series_order', $seriesOrder ?? 0);
            } else {
                \Zotlabs\Lib\IConfig::Delete($datarray, 'article', 'series');
                \Zotlabs\Lib\IConfig::Delete($datarray, 'article', 'series_order');
            }

            $result = item_store_update($datarray);

            if (!$result['success']) {
                logger('Articles::post update error: ' . ($result['message'] ?? ''), LOGGER_DEBUG);
                Response::error(500, 'Failed to update article');
            }

            // item_store_update() re-detects language from the body and would
            // clobber a manual choice — apply it as a direct write afterward.
            q("UPDATE item SET lang = '%s' WHERE id = %d AND uid = %d",
                dbesc($lang), intval($post_id), intval($uid));

            \Zotlabs\Daemon\Master::Summon(['Notifier', 'edit_post', $post_id]);

            Response::send(['uuid' => $orig[0]['uuid'], 'iid' => $post_id]);
        }

        // ── Create new article ────────────────────────────────────────────────
        $uuid = item_message_id();
        $mid  = z_root() . '/item/' . $uuid;
        $now  = datetime_convert();

        // ── Translation group ────────────────────────────────────────────────
        // A new translation of an existing article shares a group id (the
        // source article's own uuid) so siblings can be looked up later.
        $translationGroup = null;
        if ($translationOf) {
            $src = dbq("SELECT id, uuid FROM item
                WHERE uid = " . intval($uid) . "
                AND uuid = '" . dbesc($translationOf) . "'
                AND item_type = " . ITEM_TYPE_ARTICLE . "
                AND item_deleted = 0 LIMIT 1");

            if (!$src) {
                Response::error(404, 'Source article not found');
            }

            $srcId = intval($src[0]['id']);

            $existingGroup = dbq("SELECT v FROM iconfig
                WHERE iid = $srcId AND cat = 'article' AND k = 'translation_group' LIMIT 1");
            $translationGroup = $existingGroup ? $existingGroup[0]['v'] : $src[0]['uuid'];

            if (!$existingGroup) {
                \Zotlabs\Lib\IConfig::Set($srcId, 'article', 'translation_group', $translationGroup);
            }

            $dupeLang = dbq("SELECT item.lang FROM item
                INNER JOIN iconfig tg ON tg.iid = item.id AND tg.cat = 'article'
                    AND tg.k = 'translation_group' AND tg.v = '" . dbesc($translationGroup) . "'
                WHERE item.uid = " . intval($uid) . " AND item.lang = '" . dbesc($lang) . "'
                AND item.item_deleted = 0 LIMIT 1");
            if ($dupeLang) {
                Response::error(400, 'This language already has a translation in this group');
            }
        }

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
            'item_private'    => $item_private,
            'mimetype'        => $mimetype,
            'title'           => $title,
            'summary'         => $summary,
            'body'            => $body,
            'allow_cid'       => $allow_cid,
            'allow_gid'       => $allow_gid,
            'deny_cid'        => $deny_cid,
            'deny_gid'        => $deny_gid,
            'public_policy'   => $public_policy,
            'plink'           => $mid,
            'term'            => $post_tags,
            'attach'          => $attachments,
        ];

        if ($slug) {
            \Zotlabs\Lib\IConfig::Set($datarray, 'system',
                item_type_to_namespace(ITEM_TYPE_ARTICLE), $slug, true);
        }
        if ($series) {
            \Zotlabs\Lib\IConfig::Set($datarray, 'article', 'series', $series);
            \Zotlabs\Lib\IConfig::Set($datarray, 'article', 'series_order', $seriesOrder ?? 0);
        }
        if ($translationGroup) {
            \Zotlabs\Lib\IConfig::Set($datarray, 'article', 'translation_group', $translationGroup);
        }

        $result = item_store($datarray);

        if (!$result || !$result['success']) {
            logger('Articles::post create error: ' . ($result['message'] ?? ''), LOGGER_DEBUG);
            Response::error(500, 'Failed to create article');
        }

        // item_store() re-detects language from the body and would clobber a
        // manual choice — apply it as a direct write afterward.
        q("UPDATE item SET lang = '%s' WHERE id = %d AND uid = %d",
            dbesc($lang), intval($result['item_id']), intval($uid));

        \Zotlabs\Daemon\Master::Summon(['Notifier', 'wall-new', $result['item_id']]);

        Response::send([
            'uuid' => $result['item']['uuid'] ?? $uuid,
            'iid'  => intval($result['item_id']),
        ], [], 201);
    }

    // -------------------------------------------------------------------------
    // POST /api/articles/:nick/series-rename   { from, to }
    // POST /api/articles/:nick/series-reorder  { series, order: [uuid, ...] }
    // Owner-only management actions on series metadata, applied as direct
    // iconfig writes — no item content changes, so item_store*() isn't involved.
    // -------------------------------------------------------------------------

    private function renameSeries(int $uid, array $input): never
    {
        $from = trim($input['from'] ?? '');
        $to   = trim($input['to']   ?? '');

        if (!$from || !$to) {
            Response::error(400, 'from and to are required');
        }

        q("UPDATE iconfig SET v = '%s'
            WHERE cat = 'article' AND k = 'series' AND v = '%s'
            AND iid IN (SELECT id FROM item WHERE uid = %d AND item_type = %d)",
            dbesc($to), dbesc($from), intval($uid), intval(ITEM_TYPE_ARTICLE)
        );

        Response::send(['name' => $to]);
    }

    private function reorderSeries(int $uid, array $input): never
    {
        $series = trim($input['series'] ?? '');
        $order  = (array) ($input['order'] ?? []);

        if (!$series || !$order) {
            Response::error(400, 'series and order are required');
        }

        foreach (array_values($order) as $index => $articleUuid) {
            $r = dbq("SELECT id FROM item
                WHERE uid = " . intval($uid) . "
                AND uuid = '" . dbesc((string) $articleUuid) . "'
                AND item_type = " . ITEM_TYPE_ARTICLE . " LIMIT 1");
            if ($r) {
                $memberId = intval($r[0]['id']);
                \Zotlabs\Lib\IConfig::Set($memberId, 'article', 'series_order', $index + 1);
            }
        }

        Response::send(['series' => $series, 'order' => array_values($order)]);
    }



}
