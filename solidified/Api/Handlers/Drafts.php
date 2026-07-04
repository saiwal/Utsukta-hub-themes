<?php
namespace Theme\Solidified\Api\Handlers;

require_once('include/items.php');

use App;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Drafts
{
    // GET  /api/drafts[?type=post,article]  → list drafts for the local channel by content type
    // POST /api/drafts               → create a new draft
    // POST /api/drafts/:mid          → update an existing draft
    // POST /api/drafts/:mid/delete   → delete a draft

    public function get(): void
    {
        Auth::requireLocalGet();
        $uid = local_channel();

        $this->migrateLegacyDrafts($uid);

        // Comma-separated scope prefixes ("post", "article", …); default keeps
        // original behaviour. Tokens are whitelisted to [a-z]+ so they can be
        // embedded directly in the LIKE conditions below.
        $types = array_filter(
            array_map('trim', explode(',', $_GET['type'] ?? 'post')),
            fn($t) => preg_match('/^[a-z]+$/', $t)
        );
        if (!$types) {
            $types = ['post'];
        }

        $conds = [];
        foreach ($types as $t) {
            // %% survives q()'s sprintf pass as a literal %
            $conds[] = "route LIKE '%%\"scope\":\"" . $t . ":%%'";
        }
        $typeSql = '(' . implode(' OR ', $conds) . ')';

        // resource_type narrows via the uid_resource_type index; the LIKE
        // conditions then only run against the handful of draft rows
        $rows = q(
            "SELECT * FROM item
             WHERE uid = %d
               AND resource_type = 'draft'
               AND item_unpublished = 1
               AND item_deleted = 0
               AND $typeSql
             ORDER BY edited DESC",
            intval($uid)
        );

        $drafts = array_map([$this, 'formatDraft'], $rows ?: []);
        Response::send($drafts);
    }

    public function post(): void
    {
        Auth::requireLocalJson();

        // Sub-actions are safe path words; mid (a full URL) lives in the body only
        $sub = App::$argv[2] ?? '';

        if ($sub === 'delete') {
            $this->deleteDraft(Auth::$parsedBody['mid'] ?? '');
            return;
        }

        if ($sub === 'update') {
            $this->updateDraft(Auth::$parsedBody['mid'] ?? '');
            return;
        }

        $this->createDraft();
    }

    // One-time backfill: drafts created before resource_type stamping was
    // introduced need it set so the indexed listing query can find them.
    // The pconfig flag ensures the unindexed scan runs only once per channel.
    private function migrateLegacyDrafts(int $uid): void
    {
        if (get_pconfig($uid, 'solidified', 'drafts_resource_type')) {
            return;
        }

        q(
            "UPDATE item
             SET resource_type = 'draft'
             WHERE uid = %d
               AND item_unpublished = 1
               AND item_deleted = 0
               AND resource_type = ''
               AND route LIKE '%%\"scope\":%%'",
            intval($uid)
        );

        set_pconfig($uid, 'solidified', 'drafts_resource_type', 1);
    }

    // ── Handlers ──────────────────────────────────────────────────────────────

    private function createDraft(): void
    {
        $uid     = local_channel();
        $channel = App::get_channel();
        $observer = App::get_observer();
        $b       = Auth::$parsedBody;

        $content  = $b['body']     ?? '';
        $title    = trim($b['title']    ?? '');
        $summary  = trim($b['summary']  ?? '');
        $mimetype = $b['mimetype'] ?? 'text/bbcode';
        $scope    = trim($b['scope']    ?? 'post:new');
        $slug     = trim($b['slug']     ?? '');
        $category = trim($b['category'] ?? '');

        $uuid = item_message_id();
        $mid  = z_root() . '/item/' . $uuid;
        $now  = datetime_convert();
        $meta = json_encode(['scope' => $scope, 'slug' => $slug, 'category' => $category]);

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
            'mimetype'        => $mimetype,
            'title'           => $title,
            'summary'         => $summary,
            'body'            => $content,
            'route'           => $meta,
            'plink'           => $mid,
            'allow_cid'       => '',
            'allow_gid'       => '',
            'deny_cid'        => '',
            'deny_gid'        => '',
            'item_wall'       => 1,
            'item_origin'     => 1,
            'item_thread_top' => 1,
            'item_unseen'     => 0,
            'item_private'    => 0,
            'item_unpublished'=> 1,
            // Rides the existing uid_resource_type index so listing stays
            // fast on large item tables (no schema change needed)
            'resource_type'   => 'draft',
        ];

        // deliver=false, addAndSync=false — no federation, no notifications
        $result = item_store($datarray, false, false, false);

        if (!$result['success']) {
            Response::error(500, 'Failed to save draft');
        }

        Response::send(['mid' => $mid]);
    }

    private function updateDraft(string $mid): void
    {
        $uid    = local_channel();
        $midEsc = dbesc($mid);

        $existing = q(
            "SELECT * FROM item
             WHERE mid = '%s' AND uid = %d AND item_unpublished = 1 AND item_deleted = 0
             LIMIT 1",
            $midEsc,
            intval($uid)
        );

        if (!$existing) {
            Response::error(404, 'Draft not found');
        }

        $row = $existing[0];
        $b   = Auth::$parsedBody;

        $content  = array_key_exists('body',     $b) ? $b['body']              : $row['body'];
        $title    = array_key_exists('title',    $b) ? trim($b['title'])       : $row['title'];
        $summary  = array_key_exists('summary',  $b) ? trim($b['summary'])     : $row['summary'];
        $mimetype = array_key_exists('mimetype', $b) ? $b['mimetype']          : $row['mimetype'];

        $existingMeta = json_decode($row['route'] ?? '{}', true) ?? [];
        $scope    = trim($b['scope']    ?? $existingMeta['scope']    ?? '');
        $slug     = trim($b['slug']     ?? $existingMeta['slug']     ?? '');
        $category = trim($b['category'] ?? $existingMeta['category'] ?? '');
        $meta     = json_encode(['scope' => $scope, 'slug' => $slug, 'category' => $category]);

        $now = datetime_convert();
        $iid = intval($row['id']);

        q(
            "UPDATE item
             SET body = '%s', title = '%s', summary = '%s', mimetype = '%s',
                 route = '%s', edited = '%s', changed = '%s'
             WHERE id = %d AND uid = %d",
            dbesc($content), dbesc($title), dbesc($summary), dbesc($mimetype),
            dbesc($meta), dbesc($now), dbesc($now),
            $iid, intval($uid)
        );

        Response::send(['mid' => $mid]);
    }

    private function deleteDraft(string $mid): void
    {
        $uid    = local_channel();
        $midEsc = dbesc($mid);

        $existing = q(
            "SELECT id FROM item
             WHERE mid = '%s' AND uid = %d AND item_unpublished = 1 AND item_deleted = 0
             LIMIT 1",
            $midEsc,
            intval($uid)
        );

        if (!$existing) {
            Response::error(404, 'Draft not found');
        }

        // Hard delete — unpublished drafts need no federation tombstone
        q("DELETE FROM item WHERE id = %d AND uid = %d",
            intval($existing[0]['id']), intval($uid));

        Response::send(['success' => true]);
    }

    // ── Formatter ─────────────────────────────────────────────────────────────

    private function formatDraft(array $item): array
    {
        $meta     = json_decode($item['route'] ?? '{}', true) ?? [];
        $created  = (int)(strtotime($item['created']) * 1000);
        $updated  = (int)(strtotime($item['edited'])  * 1000);
        $body     = $item['body'] ?? '';

        $preview  = mb_substr(
            trim(preg_replace('/\s+/', ' ',
                strip_tags(preg_replace('/\[[\w\/]+(?:=[^\]]+)?\]/', '', $body))
            )),
            0, 80
        );

        return [
            'serverMid' => $item['mid'],
            'scope'     => $meta['scope']    ?? 'post:new',
            'slug'      => $meta['slug']     ?? '',
            'category'  => $meta['category'] ?? '',
            'created'   => $created,
            'updated'   => $updated,
            'preview'   => $preview,
            'body'      => $body,
            'title'     => $item['title']    ?? '',
            'summary'   => $item['summary']  ?? '',
            'mimetype'  => $item['mimetype'] ?? 'text/bbcode',
        ];
    }
}
