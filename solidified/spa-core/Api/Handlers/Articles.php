<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Concerns\ItemCollection;
use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\ContentTypes;

/**
 * The Articles app. Route dispatch and the article-specific parts of a save;
 * everything shared with Cards (list/detail/series queries, item formatting,
 * ACL resolution, the edit datarray) lives in ItemCollection.
 */
class Articles
{
    use ItemCollection;

    const COLL_ITEM_TYPE    = ITEM_TYPE_ARTICLE;
    const COLL_NAME         = 'article';
    const COLL_PLURAL       = 'articles';
    const COLL_GROUP        = 'series';
    const COLL_GROUP_PLURAL = 'series';

    public function get(): void
    {
        [, $profile_uid, $ob_hash, $permission_sql, $item_normal] = $this->collectionGetPreamble();

        $nick = \App::$argv[2];
        $sub  = \App::$argv[3] ?? '';

        if ($sub === 'series') {
            $seriesName = \App::$argv[4] ?? '';
            if ($seriesName) {
                $this->getGroupDetail($seriesName, $profile_uid, $ob_hash, $permission_sql . $item_normal, $nick);
            }
            $this->getGroupOverview($profile_uid, $permission_sql . $item_normal);
        }

        $identifier = $sub ?: ($_GET['uuid'] ?? '');
        if ($identifier) {
            $this->getSingle($identifier, $profile_uid, $ob_hash, $permission_sql, $nick);
        }

        $this->getList($profile_uid, $ob_hash, $permission_sql . $item_normal, $nick);
    }

    /** Article-only response keys: the translation group and the chosen language. */
    private function formatExtras(array $item, array $iconfig): array
    {
        return [
            'lang'              => $item['lang'] ?? '',
            'translation_group' => $iconfig['article/translation_group'] ?? null,
        ];
    }

    /** Siblings sharing this article's translation group. */
    private function afterSingle(array &$root, int $profile_uid, string $permission_sql, string $nick): void
    {
        $root['translations'] = [];

        if (empty($root['translation_group'])) {
            return;
        }

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
    }

    // -------------------------------------------------------------------------
    // POST /spa/articles/:nick
    // Body (JSON): { title, summary, body, slug, category, mimetype, lang, post_id?,
    //                series?, series_order?, translation_of?,
    //                contact_allow?, group_allow?, contact_deny?, group_deny?, public_policy? }
    // post_id present → edit existing article via item_store_update
    // post_id absent  → create new article via item_store
    // translation_of (create-only) → link the new article to the source article's
    // translation group. Series management actions dispatch via argv[3].
    // -------------------------------------------------------------------------

    public function post(): void
    {
        [$uid, , $channel, $input] = $this->collectionPostPreamble();

        $action = \App::$argv[3] ?? '';
        if ($action === 'series-rename') {
            $this->renameGroup($uid, $input);
        }
        if ($action === 'series-reorder') {
            $this->reorderGroup($uid, $input);
        }

        $body     = trim($input['body']     ?? '');
        $title    = escape_tags(trim($input['title']    ?? ''));
        $summary  = escape_tags(trim($input['summary']  ?? ''));
        $slug     = trim($input['slug']     ?? '');
        $category = trim($input['category'] ?? '');
        // Presence is authoritative (same convention as Item.php's editItem):
        // key absent = keep the article's stored categories, '' = clear them.
        $catsGiven = array_key_exists('category', $input);
        $mimetype = ContentTypes::validate($input['mimetype'] ?? null);
        $post_id  = intval($input['post_id'] ?? 0);
        $lang     = trim($input['lang']     ?? '');
        $series   = trim($input['series']   ?? '');
        $seriesOrder    = isset($input['series_order']) ? intval($input['series_order']) : null;
        $translationOf  = trim($input['translation_of'] ?? '');
        // Publishing controls — create-only, mirroring PostComposer/Item.php.
        // The edit branch below ignores them, so editing an article never
        // disturbs its stored expires/item_delayed/item_nocomment.
        $expire     = trim($input['expire']  ?? '');
        $createdRaw = trim($input['created'] ?? '');
        $nocomment  = !empty($input['nocomment']) ? 1 : 0;

        if (!$body) {
            Response::error(400, 'Body is required');
        }
        if (!$lang) {
            Response::error(400, 'Language is required');
        }
        if ($slug) {
            $slug = str_replace('/', '-', strtolower(\URLify::transliterate($slug)));
        }

        // ── Resolve ACL (same for create and edit) ──────────────────────────────
        $acl = $this->aclFromComposerInput($input, $channel['channel_hash']);
        [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy] = $acl;

        $attachments = $this->extractAttachments($uid, $channel, $mimetype, $body, $acl);
        $body        = $this->expandEmbedTokens($mimetype, $body);
        $post_tags   = $category ? $this->categoryTerms($uid, $channel, $category) : [];

        // ── Edit existing article ─────────────────────────────────────────────
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
            $this->setGroupIconfig($datarray, $series, $seriesOrder);

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

            Response::send(['uuid' => $datarray['uuid'], 'iid' => $post_id]);
        }

        // ── Create new article ────────────────────────────────────────────────
        $uuid = item_message_id();
        $mid  = z_root() . '/item/' . $uuid;
        $now  = datetime_convert();

        // Delayed publish ("time travel post", core feature delayed_posting):
        // a future created date stores the item with item_delayed = 1, which
        // hides it from all item_normal queries. Daemon\Cron flips the flag and
        // summons the Notifier once the publish time arrives — its query
        // (Cron.php: "where item_delayed = 1") is item_type-agnostic, so it
        // picks up articles exactly as it does posts.
        $created = $now;
        $delayed = 0;
        if ($createdRaw) {
            $ts = datetime_convert(date_default_timezone_get(), 'UTC', $createdRaw);
            if ($ts > $now) {
                $created = $ts;
                $delayed = 1;
            }
        }

        $translationGroup = $translationOf
            ? $this->resolveTranslationGroup($uid, $translationOf, $lang)
            : null;

        $datarray = [
            'aid'             => intval($channel['channel_account_id']),
            'uid'             => $uid,
            'uuid'            => $uuid,
            'mid'             => $mid,
            'parent_mid'      => $mid,
            'thr_parent'      => $mid,
            'owner_xchan'     => $channel['channel_hash'],
            'author_xchan'    => $channel['channel_hash'],
            'created'         => $created,
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
            'item_delayed'    => $delayed,
            'item_nocomment'  => $nocomment,
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

        // Core closes comments from the moment of publication when nocomment
        // is set (comments_closed = created); otherwise item_store leaves the
        // column at the DB null date (comments stay open).
        if ($nocomment) {
            $datarray['comments_closed'] = $created;
        }

        if ($expire) {
            $exp = datetime_convert(date_default_timezone_get(), 'UTC', $expire);
            if ($exp > $now) {
                $datarray['expires'] = $exp;
            }
        }

        if ($slug) {
            $this->setSlugIconfig($datarray, $slug);
        }
        if ($series) {
            $this->setGroupIconfig($datarray, $series, $seriesOrder);
        }
        if ($translationGroup) {
            \Zotlabs\Lib\IConfig::Set($datarray, 'article', 'translation_group', $translationGroup);
        }
        $this->setEmbedIconfig($datarray, $body);

        $result = item_store($datarray);

        if (!$result || !$result['success']) {
            logger('Articles::post create error: ' . ($result['message'] ?? ''), LOGGER_DEBUG);
            Response::error(500, 'Failed to create article');
        }

        // item_store() re-detects language from the body and would clobber a
        // manual choice — apply it as a direct write afterward.
        q("UPDATE item SET lang = '%s' WHERE id = %d AND uid = %d",
            dbesc($lang), intval($result['item_id']), intval($uid));

        // Delayed articles are delivered by Daemon\Cron at publish time, which
        // summons the Notifier itself — summoning here too would federate the
        // article immediately and defeat the schedule.
        if (!$delayed) {
            \Zotlabs\Daemon\Master::Summon(['Notifier', 'wall-new', $result['item_id']]);
        }

        Response::send([
            'uuid' => $result['item']['uuid'] ?? $uuid,
            'iid'  => intval($result['item_id']),
        ], [], 201);
    }

    /**
     * A new translation of an existing article shares a group id (the source
     * article's own uuid) so siblings can be looked up later. Backfills the
     * group onto the source when it doesn't have one yet, and refuses a second
     * translation into a language the group already covers.
     */
    private function resolveTranslationGroup(int $uid, string $translationOf, string $lang): string
    {
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

        return $translationGroup;
    }
}
