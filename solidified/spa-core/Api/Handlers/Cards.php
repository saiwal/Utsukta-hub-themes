<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Concerns\EmbedsItems;
use Utsukta\SpaCore\Api\Concerns\ItemCollection;
use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\ContentTypes;

/**
 * The Cards app. Route dispatch, the kanban board config and the card-specific
 * parts of a save; everything shared with Articles (list/detail/deck queries,
 * item formatting, ACL resolution, the edit datarray) lives in ItemCollection.
 */
class Cards
{
    use EmbedsItems;
    use ItemCollection;

    const COLL_ITEM_TYPE    = ITEM_TYPE_CARD;
    const COLL_NAME         = 'card';
    const COLL_PLURAL       = 'cards';
    const COLL_GROUP        = 'deck';
    const COLL_GROUP_PLURAL = 'decks';

    /** The authoring tabs a card body may come from. */
    private const TEMPLATES = ['freeform', 'quote', 'definition', 'link'];

    /** The single board pre-multi-board channels stored columns for. */
    private const LEGACY_BOARD = 'kanban';

    public function get(): void
    {
        [, $profile_uid, $ob_hash, $permission_sql, $item_normal] = $this->collectionGetPreamble();

        $nick = \App::$argv[2];
        $sub  = \App::$argv[3] ?? '';

        if ($sub === 'deck') {
            $deckName = \App::$argv[4] ?? '';
            if ($deckName) {
                $this->getGroupDetail($deckName, $profile_uid, $ob_hash, $permission_sql . $item_normal, $nick);
            }
            $this->getGroupOverview($profile_uid, $permission_sql . $item_normal);
        }

        if ($sub === 'kanban') {
            $this->getKanban($profile_uid);
        }

        $identifier = $sub ?: ($_GET['uuid'] ?? '');
        if ($identifier) {
            $this->getSingle($identifier, $profile_uid, $ob_hash, $permission_sql, $nick);
        }

        $this->getList($profile_uid, $ob_hash, $permission_sql . $item_normal, $nick);
    }

    /**
     * Card-only response key: the authoring template. It round-trips the
     * composer's tab choice and drives CardFace's back-panel variant. Bodies
     * themselves are plain core bbcode, so this can't be re-derived from them.
     */
    private function formatExtras(array $item, array $iconfig): array
    {
        return ['template' => ($iconfig['card/template'] ?? '') ?: 'freeform'];
    }

    /**
     * Backlinks: the channel's own items that embed this card.
     *
     * An expanded [card=<id>] block carries message_id='<mid>' (see
     * EmbedsItems::buildEmbedBlock), and the mid — unlike the block's link
     * attribute — survives a slug rename, so that is what we match on.
     *
     * item_permissions_sql (from the GET preamble) plus item_normal_search()
     * gate the list: a visitor never sees a mention they could not open, and
     * nobody sees one that is unpublished, delayed, hidden or moderated.
     * item_normal_search() rather than item_normal() because a mention may be
     * an article, a card or an ordinary post, and item_normal() pins a single
     * item_type.
     *
     * ponytail: LIKE '%…%' over the channel's items, no index. Fine at hub
     * scale; if it ever isn't, write an iconfig backlink row at embed time and
     * read that instead. Scoped to the card owner's own channel, so a mention
     * from another local channel doesn't show — cross-channel needs a
     * per-uid item_permissions_sql pass, which is a different query, not a
     * wider WHERE.
     */
    private function afterSingle(array &$root, int $profile_uid, string $permission_sql, string $nick): void
    {
        $root['mentioned_in'] = [];

        $mid = $root['mid'] ?? '';
        if (!$mid) {
            return;
        }

        // Escape LIKE's own wildcards before dbesc — a mid is a URL and '_' is
        // common in one.
        $needle = dbesc(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], "message_id='" . $mid . "'"));

        $rows = dbq("SELECT item.id, item.uuid, item.title, item.created, item.plink, item.item_type,
                (SELECT v FROM iconfig sl WHERE sl.iid = item.id AND sl.cat = 'system'
                    AND sl.k IN ('" . item_type_to_namespace(ITEM_TYPE_ARTICLE) . "','"
                               . item_type_to_namespace(ITEM_TYPE_CARD) . "') LIMIT 1) AS slug
            FROM item
            WHERE item.uid = " . intval($profile_uid) . "
            AND item.body LIKE '%$needle%'
            AND item.id = item.parent
            AND item.uuid != '" . dbesc($root['uuid']) . "'
            " . item_normal_search() . "
            $permission_sql
            ORDER BY item.created DESC
            LIMIT 50");

        $root['mentioned_in'] = array_map(function ($m) use ($nick) {
            $type = intval($m['item_type']);
            $path = $type === ITEM_TYPE_ARTICLE ? 'articles' : ($type === ITEM_TYPE_CARD ? 'cards' : '');

            return [
                'uuid'      => $m['uuid'],
                'title'     => Response::decodeEntities($m['title']),
                'created'   => $m['created'],
                'item_type' => $type,
                'view_url'  => $path
                    ? z_root() . '/' . $path . '/' . $nick . '/' . ($m['slug'] ? urldecode($m['slug']) : $m['uuid'])
                    : ($m['plink'] ?: z_root() . '/display/' . $m['uuid']),
            ];
        }, $rows ?: []);
    }

    // -------------------------------------------------------------------------
    // GET /spa/cards/:nick/kanban -> { enabled, boards: [{ name, columns }] }
    //
    // A board IS a category: its cards are the ones carrying `name` as a
    // category term, and its columns are their decks. Board config only — the
    // cards come from the ordinary card list (?cat=<board>), which is already
    // permission-filtered, so a visitor sees the columns but only the cards
    // they may see. Board and column names are public metadata, same as the
    // deck names in the deck overview.
    // -------------------------------------------------------------------------

    private function getKanban(int $profile_uid): never
    {
        $raw = get_pconfig($profile_uid, 'spa', 'kanban_boards', '');
        $boards = $this->normalizeBoards($raw ? (json_decode($raw, true) ?? []) : []);

        if (!$boards) {
            // Pre-multi-board channels stored one column list and no board list.
            // Read it as the legacy board rather than migrating anything. With
            // no columns either there is nothing to show: a channel that has
            // never configured a board gets none, and the SPA offers to create
            // one instead of inventing an empty "kanban" board.
            $legacy = get_pconfig($profile_uid, 'spa', 'kanban_columns', '');
            $legacy = $legacy ? (json_decode($legacy, true) ?? []) : [];
            if ($legacy) {
                $boards = $this->normalizeBoards([[
                    'name'    => self::LEGACY_BOARD,
                    'columns' => $legacy,
                ]]);
            }
        }

        Response::send([
            'enabled' => intval(get_pconfig($profile_uid, 'spa', 'kanban')) === 1,
            'boards'  => $boards,
        ]);
    }

    /** Drops anything malformed and de-duplicates by board name. */
    private function normalizeBoards(array $raw): array
    {
        $boards = [];
        $seen   = [];
        foreach ($raw as $b) {
            if (!is_array($b)) continue;
            $name = is_string($b['name'] ?? null) ? notags(trim($b['name'])) : '';
            if ($name === '' || in_array($name, $seen, true)) continue;
            $seen[] = $name;

            $columns = [];
            foreach ((array) ($b['columns'] ?? []) as $c) {
                if (!is_string($c)) continue;
                $c = notags(trim($c));
                if ($c !== '' && !in_array($c, $columns, true)) $columns[] = $c;
            }
            $boards[] = ['name' => $name, 'columns' => $columns];
        }
        return $boards;
    }

    // -------------------------------------------------------------------------
    // POST /spa/cards/:nick
    // Body (JSON): { title, summary, body, slug, category, mimetype, post_id?,
    //                deck?, deck_order?, template?,
    //                contact_allow?, group_allow?, contact_deny?, group_deny?, public_policy? }
    // post_id present → edit existing card via item_store_update
    // post_id absent  → create new card via item_store
    // Deck and board management actions dispatch via argv[3].
    // -------------------------------------------------------------------------

    public function post(): void
    {
        [$uid, , $channel, $input] = $this->collectionPostPreamble();

        $action = \App::$argv[3] ?? '';
        if ($action === 'deck-rename') {
            $this->renameGroup($uid, $input);
        }
        if ($action === 'deck-reorder') {
            $this->reorderGroup($uid, $input);
        }
        if ($action === 'deck-move') {
            $this->moveCard($uid, $input);
        }
        if ($action === 'kanban-boards') {
            $this->saveKanbanBoards($uid, $input);
        }
        if ($action === 'board-rename') {
            $this->renameBoard($uid, $input);
        }

        $body     = trim($input['body']     ?? '');
        $title    = escape_tags(trim($input['title']    ?? ''));
        $summary  = escape_tags(trim($input['summary']  ?? ''));
        $slug     = trim($input['slug']     ?? '');
        $category = trim($input['category'] ?? '');
        // Presence is authoritative (same convention as Item.php's editItem):
        // key absent = keep the card's stored categories, '' = clear them.
        $catsGiven = array_key_exists('category', $input);
        $mimetype = ContentTypes::validate($input['mimetype'] ?? null);
        $post_id  = intval($input['post_id'] ?? 0);
        $deck      = trim($input['deck'] ?? '');
        $deckOrder = isset($input['deck_order']) ? intval($input['deck_order']) : null;
        $template  = trim($input['template'] ?? '');

        if (!$body) {
            Response::error(400, 'Body is required');
        }
        if (!in_array($template, self::TEMPLATES, true)) {
            $template = 'freeform';
        }
        if ($slug) {
            $slug = str_replace('/', '-', strtolower(\URLify::transliterate($slug)));
        }

        // ── Resolve ACL (same for create and edit) ──────────────────────────────
        $acl = $this->aclFromComposerInput($input, $channel['channel_hash']);
        [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy] = $acl;

        $attachments = $this->extractAttachments($uid, $channel, $mimetype, $body, $acl);

        $body = $this->expandEmbedTokens($mimetype, $body);

        $post_tags = $category ? $this->categoryTerms($uid, $channel, $category) : [];

        // ── Edit existing card ────────────────────────────────────────────────
        if ($post_id) {
            $datarray = $this->buildEditDatarray($post_id, $uid, [
                'title'    => $title,
                'summary'  => $summary,
                'body'     => $body,
                'mimetype' => $mimetype,
            ], $post_tags, $catsGiven, $acl);

            $datarray['attach'] = $attachments;

            if ($slug) {
                $this->setSlugIconfig($datarray, $slug);
            }
            $this->setGroupIconfig($datarray, $deck, $deckOrder);
            \Zotlabs\Lib\IConfig::Set($datarray, 'card', 'template', $template);

            $result = item_store_update($datarray);

            if (!$result['success']) {
                logger('Cards::post update error: ' . ($result['message'] ?? ''), LOGGER_DEBUG);
                Response::error(500, 'Failed to update card');
            }

            \Zotlabs\Daemon\Master::Summon(['Notifier', 'edit_post', $post_id]);

            Response::send(['uuid' => $datarray['uuid'], 'iid' => $post_id]);
        }

        // ── Create new card ───────────────────────────────────────────────────
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
            'obj_type'        => 'Card',
            'item_type'       => ITEM_TYPE_CARD,
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
            $this->setSlugIconfig($datarray, $slug);
        }
        if ($deck) {
            $this->setGroupIconfig($datarray, $deck, $deckOrder);
        }
        \Zotlabs\Lib\IConfig::Set($datarray, 'card', 'template', $template);

        $result = item_store($datarray);

        if (!$result || !$result['success']) {
            logger('Cards::post create error: ' . ($result['message'] ?? ''), LOGGER_DEBUG);
            Response::error(500, 'Failed to create card');
        }

        \Zotlabs\Daemon\Master::Summon(['Notifier', 'wall-new', $result['item_id']]);

        Response::send([
            'uuid' => $result['item']['uuid'] ?? $uuid,
            'iid'  => intval($result['item_id']),
        ], [], 201);
    }

    /**
     * Move one card to a deck (kanban: to a column), or out of every deck when
     * $deck is ''. Deliberately not the full card POST above: that wants a
     * whole card payload and runs item_store_update() — a term rebuild, a
     * notifier summon and a fresh edited timestamp — for what is two iconfig
     * rows. A drag is not an edit of the card.
     */
    private function moveCard(int $uid, array $input): never
    {
        $uuid  = trim($input['uuid'] ?? '');
        $deck  = trim($input['deck'] ?? '');
        $order = intval($input['order'] ?? 0);

        if (!$uuid) {
            Response::error(400, 'uuid is required');
        }

        $r = dbq("SELECT id FROM item
            WHERE uid = " . intval($uid) . "
            AND uuid = '" . dbesc($uuid) . "'
            AND item_type = " . ITEM_TYPE_CARD . " LIMIT 1");
        if (!$r) {
            Response::error(404, 'Card not found');
        }

        $iid = intval($r[0]['id']);
        $this->setGroupIconfig($iid, $deck, $order);

        Response::send(['uuid' => $uuid, 'deck' => $deck, 'order' => $order]);
    }

    /**
     * Rename a board = retag its cards, since a board IS a category. The
     * board list itself is rewritten by the client's following kanban-boards
     * save; this only touches the term rows, and only on cards.
     */
    private function renameBoard(int $uid, array $input): never
    {
        $from = trim($input['from'] ?? '');
        $to   = trim($input['to']   ?? '');

        if (!$from || !$to) {
            Response::error(400, 'from and to are required');
        }

        $channel = channelx_by_n($uid);
        $url = channel_url($channel) . '?cat=' . urlencode($to);

        q("UPDATE term SET term = '%s', url = '%s'
            WHERE uid = %d AND otype = %d AND ttype = %d AND term = '%s'
            AND oid IN (SELECT id FROM item WHERE uid = %d AND item_type = %d)",
            dbesc($to), dbesc($url), intval($uid), intval(TERM_OBJ_POST),
            intval(TERM_CATEGORY), dbesc($from), intval($uid), intval(ITEM_TYPE_CARD)
        );

        Response::send(['name' => $to]);
    }

    /** The channel's boards and their ordered columns (pconfig spa/kanban_boards). */
    private function saveKanbanBoards(int $uid, array $input): never
    {
        $raw = $input['boards'] ?? null;
        if (!is_array($raw)) {
            Response::error(400, 'boards must be an array');
        }

        $boards = $this->normalizeBoards($raw);
        set_pconfig($uid, 'spa', 'kanban_boards', json_encode($boards));

        Response::send(['boards' => $boards]);
    }
}
