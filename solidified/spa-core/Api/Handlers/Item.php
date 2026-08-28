<?php
// packages/spa-core/php/Api/Handlers/Item.php

namespace Utsukta\SpaCore\Api\Handlers;

require_once ('include/items.php');
require_once ('include/conversation.php');
require_once ('include/security.php');
require_once ('include/crypto.php');

use Zotlabs\Daemon\Master;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Enotify;
use Zotlabs\Access\PermissionLimits;
use App;
use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Concerns\ReactionCounts;
use Utsukta\SpaCore\Api\Concerns\FiltersBlockedChannels;
use Utsukta\SpaCore\Api\Concerns\EnforcesServiceClass;
use Utsukta\SpaCore\Api\Concerns\EmbedsCards;
use Utsukta\SpaCore\Api\Response;

class Item
{
    use FiltersBlockedChannels;
    use EnforcesServiceClass;
    use EmbedsCards;

    // ── Entry points ──────────────────────────────────────────────────────────
    //
    // GET  /api/item                         -> 400
    // GET  /api/item/:mid                    -> item + thread root details
    // GET  /api/item/:mid/comments           -> all comments
    // GET  /api/item/:mid/comments/:count    -> paginated comments (?offset, ?order)
    //      /api/item/:mid/comments?around=X  -> ancestors + sibling window around comment X
    // GET  /api/item/:mid/likes              -> who liked
    // GET  /api/item/:mid/dislikes           -> who disliked
    // GET  /api/item/:mid/repeats            -> who repeated
    //
    // POST /api/item/:mid/like               -> toggle like
    // POST /api/item/:mid/dislike            -> toggle dislike
    // POST /api/item/:mid/repeat             -> toggle repeat
    // POST /api/item/:mid/star               -> toggle starred
    // POST /api/item/:mid/pin                -> toggle pinned (channel wall)
    // POST /api/item/:mid/addtocal           -> import event into viewer's calendar
    // POST /api/item/:mid/comment            -> post a comment
    // POST /api/item/:mid/delete             -> delete item
    // POST /api/item/:mid/edit               -> edit item body/title
    // POST /api/item/:mid/follow             -> follow thread (core: subthread/sub)
    // POST /api/item/:mid/unfollow           -> unfollow thread (core: subthread/unsub)
    // POST /api/item                         -> create new top-level post
    // POST /api/item/:mid                    -> (same as comment — alias)

    public function get(): void
    {
        // Hubzilla message IDs are full URLs (e.g. https://host/item/uuid) that
        // contain "/" characters. When not percent-encoded by the caller, the path
        // is split across multiple argv segments. Reconstruct the mid by detecting
        // the known verb at the end; for comments the optional count sits after it.
        $GET_VERBS = ['comments', 'likes', 'dislikes', 'repeats', 'folders', 'delivery', 'compose', 'sharepreview', 'cardpreview'];
        $segs  = array_slice(App::$argv, 2);
        $n     = count($segs);
        $verb  = '';
        $extra = 'all';

        if ($n >= 2 && in_array($segs[$n - 1], $GET_VERBS, true)) {
            $verb = $segs[$n - 1];
            $mid  = implode('/', array_slice($segs, 0, $n - 1));
        } elseif ($n >= 3 && in_array($segs[$n - 2], $GET_VERBS, true)) {
            // e.g. .../comments/5
            $verb  = $segs[$n - 2];
            $extra = $segs[$n - 1];
            $mid   = implode('/', array_slice($segs, 0, $n - 2));
        } else {
            $mid = implode('/', $segs);
        }

        $mid = self::fixProtocolSlashes($mid);

        if (!$mid) {
            json_return_and_die(['error' => 'mid required']);
        }

        switch ($verb) {
            case 'comments':
                $this->getComments($mid, $extra);
                break;
            case 'likes':
                $this->getReactions($mid, 'Like');
                break;
            case 'dislikes':
                $this->getReactions($mid, 'Dislike');
                break;
            case 'repeats':
                $this->getReactions($mid, ACTIVITY_SHARE);
                break;
            case 'folders':
                $this->getItemFolders($mid);
                break;
            case 'delivery':
                $this->getDeliveryReport($mid);
                break;
            case 'compose':
                $this->getComposeSource($mid);
                break;
            case 'sharepreview':
                $this->getSharePreview($mid);
                break;
            case 'cardpreview':
                $this->getCardPreview($mid);
                break;
            default:
                $this->getItem($mid);
                break;
        }
    }

    public function post(): void
    {
        // Reconstruct mid from all argv segments after "item", accounting for
        // message IDs that contain "/" (full zot6 URLs like https://host/item/uuid).
        // The action verb is always the last segment for POST requests.
        $POST_VERBS = ['like', 'dislike', 'repeat', 'accept', 'reject',
                       'tentativeaccept', 'star', 'pin', 'comment', 'delete',
                       'edit', 'reshare', 'saveto', 'vote',
                       'follow', 'unfollow', 'addtocal'];

        $segs = array_slice(App::$argv, 2);
        $last = count($segs) ? $segs[count($segs) - 1] : '';

        if (in_array($last, $POST_VERBS, true)) {
            $verb = $last;
            $mid  = implode('/', array_slice($segs, 0, -1));
        } else {
            $verb = '';
            $mid  = implode('/', $segs);
        }

        $mid = self::fixProtocolSlashes($mid);

        if (!$mid) {
            $this->createPost();
            return;
        }

        switch ($verb) {
            case 'like':
                $this->toggleReaction($mid, 'Like');
                break;
            case 'dislike':
                $this->toggleReaction($mid, 'Dislike');
                break;
            case 'repeat':
                $this->toggleReaction($mid, ACTIVITY_SHARE);
                break;
            case 'accept':
                $this->toggleRsvpReaction($mid, 'Accept');
                break;
            case 'reject':
                $this->toggleRsvpReaction($mid, 'Reject');
                break;
            case 'tentativeaccept':
                $this->toggleRsvpReaction($mid, 'TentativeAccept');
                break;
            case 'star':
                $this->toggleStar($mid);
                break;
            case 'pin':
                $this->togglePin($mid);
                break;
            case 'addtocal':
                $this->addToCalendar($mid);
                break;
            case 'comment':
                $this->createComment($mid);
                break;
            case 'delete':
                $this->deleteItem($mid);
                break;
            case 'edit':
                $this->editItem($mid);
                break;
            case 'reshare':
                $this->createReshare($mid);
                break;
            case 'saveto':
                $this->saveToFolder($mid);
                break;
            case 'vote':
                $this->voteOnPoll($mid);
                break;
            case 'follow':
                $this->toggleThreadFollow($mid, true);
                break;
            case 'unfollow':
                $this->toggleThreadFollow($mid, false);
                break;
            default:
                // POST /api/item/:mid with no verb → comment (convenience alias)
                $this->createComment($mid);
                break;
        }
    }

    // =========================================================================
    // GET handlers
    // =========================================================================

    // GET /api/item/:mid
    // Returns the thread root item. Comments are NOT inlined — fetch separately.
    private function getItem(string $mid): void
    {
        $ob_hash = get_observer_hash();
        $item_normal = item_normal();

        $item = $this->resolveItem($mid, $ob_hash);
        if (!$item) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        // Re-fetch with reaction subqueries now that we have the parent id
        $iid = intval($item['id']);
        $rows = dbq('SELECT item.*,
            ' . self::reactionSubqueries() . "
            FROM item
            WHERE item.id = $iid
            $item_normal
            LIMIT 1");

        if (!$rows) {
            json_return_and_die(['error' => 'Item not found']);
        }

        xchan_query($rows, true);
        $rows = fetch_post_tags($rows, true);

        $row = $rows[0];
        // Follow/Ignore activities live in the *viewer's* channel copy of the
        // thread (core Mod_Subthread), so match by parent_mid within their uid.
        $luid = intval(local_channel());
        if ($luid && $ob_hash) {
            $pmid = dbesc($row['parent_mid']);
            $obs  = dbesc($ob_hash);
            $fr = dbq(
                "SELECT verb FROM item
                 WHERE uid = $luid
                   AND parent_mid = '$pmid'
                   AND author_xchan = '$obs'
                   AND verb IN ('Follow', 'Ignore')
                   AND item_deleted = 0
                 ORDER BY created DESC, id DESC LIMIT 1"
            );
            if (!empty($fr)) {
                $row['viewer_following'] = $fr[0]['verb'] === 'Follow';
            } else {
                // No explicit Follow/Ignore yet — commenting on the thread
                // already makes core notify() treat you as involved, so
                // reflect that here too (see applyViewerFollowing()).
                $participated = dbq(
                    "SELECT id FROM item
                     WHERE uid = $luid
                       AND parent_mid = '$pmid'
                       AND author_xchan = '$obs'
                       AND verb NOT IN ('Follow', 'Ignore')
                       AND item_deleted = 0
                     LIMIT 1"
                );
                $row['viewer_following'] = !empty($participated);
            }
        }

        json_return_and_die(['item' => self::formatItem($row, $ob_hash)]);
    }

    // GET /api/item/:mid/comments
    // GET /api/item/:mid/comments/:count   (:count = integer or "all")
    //
    // Comment view mode (the "thread_mode" display setting) picks between two
    // shapes, both paginated:
    //   roots_offset, order=oldest_first|newest_first, branch_limit
    //        -- threaded mode: pages through TOP-LEVEL comments (numeric
    //           :count = roots_limit); each returned root comment is bundled
    //           with up to branch_limit of its own earliest descendants (same
    //           fixed count by default). A comment's initial replies always
    //           arrive in the SAME response as the comment itself — an
    //           earlier attempt at slicing branches independently of their
    //           root could return a reply on one page while its (real,
    //           non-deleted) parent was already shown on a previous page,
    //           and the client had no way to tell "parent fetched earlier"
    //           from "parent deleted".
    //   branch=<mid>, branch_offset, branch_limit
    //        -- threaded mode: "load more replies" for ONE specific comment's
    //           own subtree, continuing from branch_offset — independent of
    //           root paging. Uses the same breadth-first collectSubtree()
    //           traversal as the initial branch slice above, so a node is
    //           only ever included once its own parent has already been
    //           included (a structural guarantee, not a timestamp
    //           assumption — see collectSubtree()'s doc comment).
    //   flat=1, offset, order
    //        -- list mode: simple pagination over ALL comments regardless of
    //           nesting depth (numeric :count = limit) — no root/branch
    //           structure at all, since the flat view doesn't render
    //           parent/child relationships, there's nothing for a partial
    //           fetch to get inconsistent about.
    //   around=<mid|uuid>, before, after
    //        -- ancestors + a sibling window around one comment (permalink open)
    private function getComments(string $mid, string $count): void
    {
        $ob_hash = get_observer_hash();

        $root = $this->resolveItem($mid, $ob_hash);
        if (!$root) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        // A viewable root does not make its children viewable — a comment can
        // carry a narrower ACL than the thread it hangs off. Core applies the
        // same clause to the children (items_by_parent_ids' $permission_sql).
        // Folded into $item_normal because every comment mode below already
        // threads that fragment into its query.
        $item_normal = item_normal() . item_permissions_sql(intval($root['uid']), $ob_hash);

        $rootId = intval($root['id']);

        $blocked = $this->blockedXchans(local_channel());
        $blocked_sql = $this->blockedSqlClause('item.author_xchan', $blocked)
            . $this->blockedSqlClause('item.owner_xchan', $blocked);

        $around = trim((string)($_GET['around'] ?? ''));
        // Permalinks from classic notifications carry a b64.-encoded mid.
        if (str_starts_with($around, 'b64.')) {
            $around = (string) unpack_link_id($around);
        }
        if ($around !== '') {
            json_return_and_die(
                $this->buildCommentContext($root, $rootId, $ob_hash, $item_normal, $blocked_sql, $around)
            );
        }

        $branch = trim((string)($_GET['branch'] ?? ''));
        if ($branch !== '') {
            json_return_and_die(
                $this->buildBranchPage($root, $rootId, $ob_hash, $item_normal, $blocked_sql, $branch)
            );
        }

        if ($count === 'all' || !is_numeric($count)) {
            [$comments, $deletedStubs] = $this->fetchAndFormatComments(
                $rootId, $root['mid'], $ob_hash, $item_normal, $blocked_sql, 'ORDER BY item.created ASC'
            );
            json_return_and_die([
                'mid'      => $root['mid'],
                'total'    => count($comments),
                'comments' => array_merge($comments, $deletedStubs),
            ]);
        }

        $limit = max(1, intval($count));

        if (trim((string)($_GET['flat'] ?? '')) !== '') {
            json_return_and_die(
                $this->buildFlatPage($root, $rootId, $ob_hash, $item_normal, $blocked_sql, $limit)
            );
        }

        json_return_and_die(
            $this->buildRootsPage($root, $rootId, $ob_hash, $item_normal, $blocked_sql, $limit)
        );
    }

    // Shared thread-comments fetch: same WHERE shape as the flat query above,
    // with a caller-supplied trailing ORDER/LIMIT clause and optional extra
    // filter (e.g. "AND item.mid IN (...)" for the context window below).
    // Formats rows and resolves deleted-parent stubs. Used by both the flat
    // and the sibling-window comment modes so they share one implementation.
    private function fetchAndFormatComments(int $rootId, string $rootMid, string $ob_hash, string $item_normal, string $blocked_sql, string $trailingSql, string $extraWhere = ''): array
    {
        $rows = dbq('SELECT item.*,
            ' . self::reactionSubqueries() . "
            FROM item
            WHERE item.parent = $rootId
              AND item.verb IN ('Create', 'Update', 'EmojiReact')
              AND item.obj_type NOT IN ('Answer')
              AND item.item_thread_top = 0
              $item_normal
              $blocked_sql
              $extraWhere
            $trailingSql");

        if ($rows) {
            xchan_query($rows, true);
            $rows = fetch_post_tags($rows, true);
        }

        $comments = array_map(
            fn($row) => self::formatItem($row, $ob_hash),
            $rows ?: []
        );

        return [$comments, self::findDeletedParentStubs($comments, $rootMid)];
    }

    // Cheap whole-thread shape query (id/uuid/mid/thr_parent/created only, no
    // reaction subqueries, no formatItem) — used to compute root/branch paging
    // and the sibling-window context in PHP without formatting (and paying the
    // per-row can_comment_on_post() permission check for) every row up front.
    private function fetchThinThread(int $rootId, string $item_normal, string $blocked_sql): array
    {
        return dbq("SELECT id, uuid, mid, thr_parent, created
            FROM item
            WHERE item.parent = $rootId
              AND item.verb IN ('Create', 'Update', 'EmojiReact')
              AND item.obj_type NOT IN ('Answer')
              AND item.item_thread_top = 0
              $item_normal
              $blocked_sql
            ORDER BY item.created ASC, item.id ASC") ?: [];
    }

    // Groups thin rows by their immediate thr_parent mid.
    private function groupByParent(array $thin): array
    {
        $out = [];
        foreach ($thin as $row) {
            $out[$row['thr_parent'] ?? ''][] = $row;
        }
        return $out;
    }

    // All descendants of $mid (not including $mid itself), across every
    // nesting level — the complete subtree, always fetched in full (see
    // getComments()'s doc comment for why branches are never sliced).
    private function collectSubtree(string $mid, array $childrenOf): array
    {
        $result = [];
        $queue = $childrenOf[$mid] ?? [];
        while ($queue) {
            $node = array_shift($queue);
            $result[] = $node;
            foreach ($childrenOf[$node['mid']] ?? [] as $child) {
                $queue[] = $child;
            }
        }
        return $result;
    }

    // roots_offset/roots_limit mode (threaded view): a page of TOP-LEVEL
    // comments, each bundled with up to branch_limit of its own earliest
    // descendants — not the whole subtree. Each root comment's remaining
    // replies (if any) page independently via buildBranchPage()'s branch=
    // mode once the user expands that specific comment further.
    private function buildRootsPage(array $root, int $rootId, string $ob_hash, string $item_normal, string $blocked_sql, int $rootsLimit): array
    {
        $order       = (($_GET['order'] ?? '') === 'newest_first') ? 'DESC' : 'ASC';
        $rootsOffset = max(0, intval($_GET['roots_offset'] ?? 0));
        $branchLimit = max(1, intval($_GET['branch_limit'] ?? $rootsLimit));

        $thin = $this->fetchThinThread($rootId, $item_normal, $blocked_sql);
        $childrenOf = $this->groupByParent($thin);

        $rootComments = $childrenOf[$root['mid']] ?? [];
        if ($order === 'DESC') $rootComments = array_reverse($rootComments);

        $totalRoots = count($rootComments);
        $page = array_slice($rootComments, $rootsOffset, $rootsLimit);

        $neededMids = array_column($page, 'mid');
        $branches = [];
        foreach ($page as $rc) {
            $subtree = $this->collectSubtree($rc['mid'], $childrenOf);
            $slice = array_slice($subtree, 0, $branchLimit);
            $neededMids = array_merge($neededMids, array_column($slice, 'mid'));
            $branches[$rc['mid']] = [
                'fetched'     => count($slice),
                'next_offset' => count($slice),
                'total'       => count($subtree),
                'has_more'    => count($subtree) > count($slice),
            ];
        }
        $neededMids = array_values(array_unique($neededMids));
        $extraWhere = $neededMids
            ? "AND item.mid IN ('" . implode("','", array_map('dbesc', $neededMids)) . "')"
            : 'AND 1=0';

        [$comments, $deletedStubs] = $this->fetchAndFormatComments(
            $rootId, $root['mid'], $ob_hash, $item_normal, $blocked_sql, 'ORDER BY item.created ASC', $extraWhere
        );

        return [
            'mode'              => 'roots',
            'mid'               => $root['mid'],
            'total'             => count($comments),
            'comments'          => array_merge($comments, $deletedStubs),
            'roots_offset'      => $rootsOffset,
            'roots_limit'       => $rootsLimit,
            'roots_fetched'     => count($page),
            'next_roots_offset' => $rootsOffset + count($page),
            'total_roots'       => $totalRoots,
            'order'             => ($order === 'DESC') ? 'newest_first' : 'oldest_first',
            'has_more_roots'    => $rootsOffset + count($page) < $totalRoots,
            'branch_limit'      => $branchLimit,
            'branches'          => $branches,
        ];
    }

    // branch=<mid> mode (threaded view): "load more replies" for one
    // specific comment's own subtree, continuing from $branchOffset —
    // independent of root paging.
    private function buildBranchPage(array $root, int $rootId, string $ob_hash, string $item_normal, string $blocked_sql, string $branch): array
    {
        $branchOffset = max(0, intval($_GET['branch_offset'] ?? 0));
        $branchLimit  = max(1, intval($_GET['branch_limit'] ?? 5));

        $thin = $this->fetchThinThread($rootId, $item_normal, $blocked_sql);
        $childrenOf = $this->groupByParent($thin);

        $byMid = [];
        foreach ($thin as $row) {
            $byMid[$row['mid']] = $row;
        }

        if (!isset($byMid[$branch])) {
            return [
                'mode' => 'branch', 'mid' => $root['mid'], 'branch' => $branch,
                'comments' => [], 'branch_offset' => $branchOffset, 'branch_limit' => $branchLimit,
                'fetched' => 0, 'next_branch_offset' => $branchOffset, 'total' => 0, 'has_more' => false,
            ];
        }

        $subtree = $this->collectSubtree($branch, $childrenOf);
        $slice = array_slice($subtree, $branchOffset, $branchLimit);
        $neededMids = array_column($slice, 'mid');

        [$comments, $deletedStubs] = $neededMids
            ? $this->fetchAndFormatComments(
                $rootId, $root['mid'], $ob_hash, $item_normal, $blocked_sql, 'ORDER BY item.created ASC',
                "AND item.mid IN ('" . implode("','", array_map('dbesc', $neededMids)) . "')"
            )
            : [[], []];

        return [
            'mode'               => 'branch',
            'mid'                => $root['mid'],
            'branch'             => $branch,
            'comments'           => array_merge($comments, $deletedStubs),
            'branch_offset'      => $branchOffset,
            'branch_limit'       => $branchLimit,
            'fetched'            => count($slice),
            'next_branch_offset' => $branchOffset + count($slice),
            'total'              => count($subtree),
            'has_more'           => $branchOffset + count($slice) < count($subtree),
        ];
    }

    // flat=1 mode (list view): simple offset/limit pagination over ALL
    // comments regardless of nesting depth — no root/branch structure. Safe
    // to slice arbitrarily since the list view never renders parent/child
    // relationships, so there's no tree consistency to preserve across pages.
    private function buildFlatPage(array $root, int $rootId, string $ob_hash, string $item_normal, string $blocked_sql, int $limit): array
    {
        $order  = (($_GET['order'] ?? '') === 'newest_first') ? 'DESC' : 'ASC';
        $offset = max(0, intval($_GET['offset'] ?? 0));

        [$comments] = $this->fetchAndFormatComments(
            $rootId, $root['mid'], $ob_hash, $item_normal, $blocked_sql,
            "ORDER BY item.created $order LIMIT $limit OFFSET $offset"
        );

        return [
            'mode'        => 'flat',
            'mid'         => $root['mid'],
            'total'       => count($comments),
            'comments'    => $comments,
            'offset'      => $offset,
            'limit'       => $limit,
            'next_offset' => $offset + count($comments),
            'order'       => ($order === 'DESC') ? 'newest_first' : 'oldest_first',
            'has_more'    => count($comments) >= $limit,
        ];
    }

    // around=<mid|uuid> mode: returns the target comment's ancestor chain up to
    // the thread root, plus a window of sibling replies (comments sharing the
    // target's immediate thr_parent) before/after it — not the whole thread.
    //
    // Two passes: the cheap thin query above (so cost doesn't scale with
    // thread size) to walk the ancestor chain and locate the sibling window in
    // PHP, then a full formatItem-scoped query limited to just the mids needed.
    private function buildCommentContext(array $root, int $rootId, string $ob_hash, string $item_normal, string $blocked_sql, string $target): array
    {
        $before = max(0, intval($_GET['before'] ?? 3));
        $after  = max(0, intval($_GET['after'] ?? 3));

        $thinRows = $this->fetchThinThread($rootId, $item_normal, $blocked_sql);

        $byMid = [];
        $targetRow = null;
        foreach ($thinRows as $row) {
            $byMid[$row['mid']] = $row;
            if ($row['mid'] === $target || $row['uuid'] === $target) {
                $targetRow = $row;
            }
        }

        if (!$targetRow) {
            // Target missing (deleted, bad id, ...) — degrade to a normal first
            // page rather than failing the whole view.
            $limitN = 20;
            [$comments, $deletedStubs] = $this->fetchAndFormatComments($rootId, $root['mid'], $ob_hash, $item_normal, $blocked_sql, "ORDER BY item.created ASC LIMIT $limitN");
            return [
                'mode'         => 'context',
                'mid'          => $root['mid'],
                'total'        => count($comments),
                'comments'     => array_merge($comments, $deletedStubs),
                'target_mid'   => $target,
                'target_found' => false,
                'offset'       => 0,
                'limit'        => $limitN,
                'order'        => 'oldest_first',
                'has_more'     => count($comments) >= $limitN,
            ];
        }

        // Walk thr_parent up to the thread root, collecting ancestor mids
        // (root-to-target order).
        $ancestorMids = [];
        $cur = $targetRow['thr_parent'] ?? '';
        while ($cur !== '' && $cur !== $root['mid'] && isset($byMid[$cur])) {
            $ancestorMids[] = $cur;
            $cur = $byMid[$cur]['thr_parent'] ?? '';
        }
        $ancestorMids = array_reverse($ancestorMids);

        // Sibling window: comments sharing the target's immediate parent.
        $siblingParent = $targetRow['thr_parent'] ?? '';
        $siblings = array_values(array_filter(
            $thinRows,
            fn($r) => ($r['thr_parent'] ?? '') === $siblingParent
        ));
        $idx = 0;
        foreach ($siblings as $i => $s) {
            if ($s['mid'] === $targetRow['mid']) {
                $idx = $i;
                break;
            }
        }
        $start = max(0, $idx - $before);
        $end   = min(count($siblings), $idx + $after + 1);
        $windowMids = array_column(array_slice($siblings, $start, $end - $start), 'mid');

        $neededMids = array_values(array_unique(array_merge($ancestorMids, [$targetRow['mid']], $windowMids)));
        $inList = implode("','", array_map('dbesc', $neededMids));
        $extraWhere = $neededMids ? "AND item.mid IN ('$inList')" : 'AND 1=0';

        [$comments, $deletedStubs] = $this->fetchAndFormatComments($rootId, $root['mid'], $ob_hash, $item_normal, $blocked_sql, 'ORDER BY item.created ASC', $extraWhere);

        return [
            'mode'               => 'context',
            'mid'                => $root['mid'],
            'total'              => count($comments),
            'comments'           => array_merge($comments, $deletedStubs),
            'target_mid'         => $targetRow['mid'],
            'target_found'       => true,
            'ancestor_mids'      => $ancestorMids,
            'sibling_thr_parent' => $siblingParent,
            'has_more_before'    => $start > 0,
            'has_more_after'     => $end < count($siblings),
        ];
    }

    // GET /api/item/:mid/likes|dislikes|repeats
    // Returns the xchan profiles of reactors — useful for "who liked this" popups
    private function getReactions(string $mid, string $activityVerb): void
    {
        $ob_hash = get_observer_hash();

        $root = $this->resolveItem($mid, $ob_hash);
        if (!$root) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        // Same as getComments(): reactions inherit no visibility from the root.
        $item_normal = item_normal() . item_permissions_sql(intval($root['uid']), $ob_hash);

        $rootMid = dbesc($root['mid']);
        $rootUid = intval($root['uid']);
        $verb = dbesc($activityVerb);

        $blocked = $this->blockedXchans(local_channel());
        $blocked_sql = $this->blockedSqlClause('item.author_xchan', $blocked);

        $rows = dbq("SELECT DISTINCT item.author_xchan, MIN(item.created) AS created
                     FROM item
                     WHERE item.uid = $rootUid
                       AND item.thr_parent = '$rootMid'
                       AND item.verb = '$verb'
                       AND item.item_deleted = 0
                       $item_normal
                       $blocked_sql
                     GROUP BY item.author_xchan
                     ORDER BY MIN(item.created) ASC");

        if (!$rows) {
            json_return_and_die(['reactions' => [], 'total' => 0]);
        }

        xchan_query($rows, true);

        $out = array_map(fn($r) => [
            'name' => Response::decodeEntities($r['author']['xchan_name'] ?? ''),
            'address' => $r['author']['xchan_addr'] ?? '',
            'url' => $r['author']['xchan_url'] ?? '',
            'photo' => $r['author']['xchan_photo_m'] ?? '',
            'created' => $r['created'],
        ], $rows);

        json_return_and_die(['reactions' => $out, 'total' => count($out)]);
    }

    // =========================================================================
    // POST handlers
    // =========================================================================

    // POST /api/item
    // Body: { profile_uid, body, title?, scope?, summary?, category?, expire?,
    //         contact_allow?, group_allow?, contact_deny?, group_deny?,
    //         poll_answers?, poll_expire_value?, poll_expire_unit? }
    // scope: "public" | "contacts" | "private" | "custom"
    // For scope="custom" supply contact_allow/group_allow arrays of xchan hashes/group ids.
    private function createPost(): void
    {
        $uid  = Auth::requireLocalJson();
        $body = Auth::$parsedBody;

        // Quota check happens before any other work, matching core's own
        // Zotlabs\Module\Item::post() placement (checked against the poster's
        // own channel, not the wall owner's, for wall-to-wall posts).
        $this->checkTopLevelItemLimit($uid, false);

        $content    = trim($body['body']        ?? '');
        $title      = trim($body['title']       ?? '');
        $summary    = trim($body['summary']     ?? '');
        $category   = trim($body['category']    ?? '');
        $profileUid = intval($body['profile_uid'] ?? $uid);
        $scope      = $body['scope']    ?? 'contacts';
        $mimetype   = $body['mimetype'] ?? 'text/bbcode';
        $expire     = trim($body['expire']      ?? '');
        $location   = escape_tags(trim($body['location'] ?? ''));
        $coord      = escape_tags(trim($body['coord']    ?? ''));
        $nocomment  = !empty($body['nocomment']) ? 1 : 0;
        $createdRaw = trim($body['created'] ?? '');

        // "Local-only" post: stored with a real ACL (so item_permissions_sql()
        // still grants access to visitors it allows) but never handed to the
        // Notifier — it only surfaces by visiting this channel directly, never
        // in anyone's Network stream or notifications. Gated server-side on
        // the poster's own pconfig opt-in (Settings → Privacy) — the client
        // flag alone is not trusted, so a disabled toggle can't be bypassed
        // by calling the API directly.
        $localOnly = (!empty($body['local_only']) && get_pconfig($uid, 'spa', 'local_only_posts')) ? 1 : 0;

        if (!$content) {
            Response::error(400, 'body is required');
        }

        $observer = App::get_observer();
        if (!$observer) {
            Response::error(403, 'Authentication required');
        }
        $ob_hash = $observer['xchan_hash'];

        if (!perm_is_allowed($profileUid, $ob_hash, 'post_wall')) {
            Response::error(403, 'Permission denied');
        }

        // Load wall owner's channel record (may differ from the logged-in channel)
        require_once('include/channel.php');
        $r = q('SELECT * FROM channel WHERE channel_id = %d LIMIT 1', $profileUid);
        if (!$r) {
            Response::error(404, 'Channel not found');
        }
        $ownerChannel = $r[0];

        // Wall-to-wall: author differs from wall owner
        $wallToWall = ($ownerChannel['channel_hash'] !== $ob_hash);

        // ACL: W2W always uses the wall owner's channel defaults.
        // For owner posts, apply the scope the client requested.
        $acl = new \Zotlabs\Access\AccessList($ownerChannel);

        if (!$wallToWall) {
            if ($scope === 'public') {
                $acl->set(['allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => '']);
            } elseif ($scope === 'private') {
                $acl->set(['allow_cid' => '<' . $ownerChannel['channel_hash'] . '>', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => '']);
            } elseif ($scope === 'custom') {
                $contactAllow = is_array($body['contact_allow'] ?? null) ? $body['contact_allow'] : [];
                $groupAllow   = is_array($body['group_allow']   ?? null) ? $body['group_allow']   : [];
                $contactDeny  = is_array($body['contact_deny']  ?? null) ? $body['contact_deny']  : [];
                $groupDeny    = is_array($body['group_deny']    ?? null) ? $body['group_deny']    : [];
                if (!$contactAllow && !$groupAllow) {
                    Response::error(400, 'Select at least one connection or group to allow.');
                }
                $acl->set([
                    'allow_cid' => implode('', array_map(fn($h) => '<' . $h . '>', $contactAllow)),
                    'allow_gid' => implode('', array_map(fn($g) => '<' . $g . '>', $groupAllow)),
                    'deny_cid'  => implode('', array_map(fn($h) => '<' . $h . '>', $contactDeny)),
                    'deny_gid'  => implode('', array_map(fn($g) => '<' . $g . '>', $groupDeny)),
                ]);
            }
            // 'contacts': keep the channel's default ACL from the AccessList constructor
        }

        // Wall-to-wall post to a forum ("group actor") channel: classic Hubzilla
        // silently converts this into a direct message addressed to the forum's
        // own xchan. item_store() below calls tag_deliver(), which already knows
        // how to re-broadcast such a DM under the forum's identity to its
        // followers (include/items.php, group-DM delivery branch) — no delivery
        // code needed here, only the ACL/private-flag conversion that makes the
        // item qualify.
        if ($wallToWall && get_pconfig($profileUid, 'system', 'group_actor')) {
            $acl->set([
                'allow_cid' => '<' . $ownerChannel['channel_hash'] . '>',
                'allow_gid' => '',
                'deny_cid'  => '',
                'deny_gid'  => '',
            ]);
        }

        // Derive public_policy and comment_policy from the wall owner's permission limits
        $viewPolicy    = PermissionLimits::Get($profileUid, 'view_stream');
        $commentPolicy = PermissionLimits::Get($profileUid, 'post_comments');
        $publicPolicy  = map_scope($viewPolicy, true);

        $gacl            = $acl->get();
        $strContactAllow = $gacl['allow_cid'];
        $strGroupAllow   = $gacl['allow_gid'];
        $strContactDeny  = $gacl['deny_cid'];
        $strGroupDeny    = $gacl['deny_gid'];

        $private = intval($acl->is_private() || $publicPolicy);

        // A specific ACL overrides public_policy (same logic as core Item::post)
        if (!empty_acl(['allow_cid' => $strContactAllow, 'allow_gid' => $strGroupAllow,
                        'deny_cid'  => $strContactDeny,  'deny_gid'  => $strGroupDeny])) {
            $publicPolicy = '';
        }

        $postTags    = [];
        $attachments = [];

        if ($mimetype === 'text/bbcode') {
            require_once('include/text.php');

            $content = cleanup_bbcode($content);

            // Linkify @mentions, #tags, !groups — modifies $content in place
            $results = linkify_tags($content, $profileUid);
            if ($results) {
                set_linkified_perms($results, $strContactAllow, $strGroupAllow, $profileUid, $private, false);
                foreach ($results as $result) {
                    $s = $result['success'];
                    if ($s['replaced']) {
                        $postTags[] = [
                            'uid'   => $profileUid,
                            'ttype' => $s['termtype'],
                            'otype' => TERM_OBJ_POST,
                            'term'  => $s['term'],
                            'url'   => $s['url'],
                        ];
                    }
                }
            }

            // Contact-allow without group-allow → direct message between individuals
            if ($strContactAllow && !$strGroupAllow) {
                $private = 2;
            }

            // Sync file/photo ACL to match the post's final ACL
            fix_attached_permissions($profileUid, $content, $strContactAllow, $strGroupAllow, $strContactDeny, $strGroupDeny);

            // Extract [attachment] tags → attach array, strip them from body
            if (preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/', $content, $match)) {
                require_once('include/attach.php');
                foreach ($match[2] as $i => $mtch) {
                    $hash = substr($mtch, 0, strpos($mtch, ','));
                    $rev  = intval(substr($mtch, strpos($mtch, ',')));
                    $r    = attach_by_hash_nodata($hash, $ob_hash, $rev);
                    if ($r['success']) {
                        $attachments[] = [
                            'href'     => z_root() . '/attach/' . $r['data']['hash'],
                            'length'   => $r['data']['filesize'],
                            'type'     => $r['data']['filetype'],
                            'title'    => urlencode($r['data']['filename']),
                            'revision' => $r['data']['revision'],
                        ];
                    }
                    $content = str_replace($match[1][$i], '', $content);
                }
            }

            $content = $this->expandShareTags($content);
            $content = $this->expandCardTags($content);

            $postTags = array_merge($postTags, self::buildEmojiTerms($profileUid, $content));
        }

        // Categories → term records (federate correctly via datarray['term'])
        if ($category) {
            foreach (array_filter(array_map('trim', explode(',', $category))) as $cat) {
                $postTags[] = [
                    'uid'   => $profileUid,
                    'ttype' => TERM_CATEGORY,
                    'otype' => TERM_OBJ_POST,
                    'term'  => $cat,
                    'url'   => channel_url($ownerChannel) . '?cat=' . urlencode($cat),
                ];
            }
        }

        $channel = App::get_channel();
        $uuid    = item_message_id();
        $mid     = z_root() . '/item/' . $uuid;
        $now     = datetime_convert();

        // Delayed publish ("time travel post", core feature delayed_posting):
        // a future created date stores the item with item_delayed = 1, which
        // hides it from all item_normal queries. Daemon\Cron flips the flag and
        // summons the Notifier once the publish time arrives.
        $created = $now;
        $delayed = 0;
        if ($createdRaw) {
            $ts = datetime_convert(date_default_timezone_get(), 'UTC', $createdRaw);
            if ($ts > $now) {
                $created = $ts;
                $delayed = 1;
            }
        }

        $datarray = [
            'aid'             => $channel['channel_account_id'],
            'uid'             => $profileUid,
            'uuid'            => $uuid,
            'mid'             => $mid,
            'parent_mid'      => $mid,
            'thr_parent'      => $mid,
            'owner_xchan'     => $ownerChannel['channel_hash'],
            'author_xchan'    => $ob_hash,
            'created'         => $created,
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
            'location'        => $location,
            'coord'           => $coord,
            'allow_cid'       => $strContactAllow,
            'allow_gid'       => $strGroupAllow,
            'deny_cid'        => $strContactDeny,
            'deny_gid'        => $strGroupDeny,
            'attach'          => $attachments,
            'term'            => array_unique($postTags, SORT_REGULAR),
            'item_wall'       => 1,
            'item_origin'     => 1,
            'item_thread_top' => 1,
            'item_unseen'     => ($wallToWall ? 1 : 0),
            'item_private'    => $private,
            'item_delayed'    => $delayed,
            'item_nocomment'  => $nocomment,
            'public_policy'   => $publicPolicy,
            'comment_policy'  => map_scope($commentPolicy),
            'plink'           => $mid,
            'route'           => '',
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

        // Polls
        $pollAnswers = $body['poll_answers'] ?? null;
        if (is_array($pollAnswers)) {
            $answers = array_values(array_filter(array_map(fn($a) => escape_tags(trim($a)), $pollAnswers)));
            if (count($answers) >= 2) {
                $expireValue = max(1, intval($body['poll_expire_value'] ?? 1));
                $expireUnit  = in_array($body['poll_expire_unit'] ?? 'Days', ['Minutes', 'Hours', 'Days', 'Weeks'], true)
                    ? $body['poll_expire_unit']
                    : 'Days';
                $opts        = array_map(
                    fn($a) => ['name' => $a, 'type' => 'Note', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                    $answers
                );
                $pollEndTime = datetime_convert(date_default_timezone_get(), 'UTC',
                    'now + ' . $expireValue . ' ' . $expireUnit, ATOM_TIME);
                $datarray['obj_type'] = 'Question';
                $datarray['obj']      = [
                    'type'         => 'Question',
                    'id'           => $mid,
                    'url'          => $mid,
                    'attributedTo' => channel_url($ownerChannel),
                    'content'      => bbcode($content),
                    'name'         => $title ?: '',
                    'oneOf'        => $opts,
                    'endTime'      => $pollEndTime,
                    'to'           => [ACTIVITY_PUBLIC_INBOX],
                ];
                if (empty($datarray['expires'])) {
                    $datarray['expires'] = datetime_convert('UTC', 'UTC', $pollEndTime);
                }
            }
        }

        call_hooks('post_local', $datarray);

        if (!empty($datarray['cancel'])) {
            Response::error(400, 'Post cancelled');
        }

        $post = item_store($datarray);

        if (!$post['success']) {
            Response::error(500, 'Failed to create post');
        }

        if ($localOnly) {
            set_iconfig(intval($post['item_id']), 'spa', 'local_only', 1);
        }

        // Notify wall owner when someone posts on their wall (wall-to-wall)
        if ($wallToWall) {
            Enotify::submit([
                'type'       => NOTIFY_WALL,
                'from_xchan' => $ob_hash,
                'to_xchan'   => $ownerChannel['channel_hash'],
                'item'       => $datarray,
                'link'       => z_root() . '/display/' . $uuid,
                'verb'       => 'Create',
                'otype'      => 'item',
            ]);
        } else {
            // Update owner's last-post timestamp
            q("UPDATE channel SET channel_lastpost = '%s' WHERE channel_id = %d",
                dbesc($now), $profileUid);
        }

        $datarray['id'] = $post['item_id'];
        call_hooks('post_local_end', $datarray);

        // Delayed items are delivered by Daemon\Cron at publish time. Local-only
        // items are never delivered at all (see $localOnly above).
        if (!$delayed && !$localOnly) {
            Master::Summon(['Notifier', 'wall-new', $post['item_id']]);
        }

        // Fetch the stored item back fully formatted and return it
        $iid  = intval($post['item_id']);
        $rows = dbq('SELECT item.*, ' . self::reactionSubqueries() . " FROM item WHERE item.id = $iid LIMIT 1");
        if ($rows) {
            xchan_query($rows, true);
            $rows          = fetch_post_tags($rows, true);
            $formattedPost = self::formatItem($rows[0], $ob_hash);
        } else {
            $formattedPost = ['iid' => $iid, 'mid' => $mid, 'uuid' => $uuid];
        }

        Response::send(['post' => $formattedPost, 'comments' => []]);
    }

    // POST /api/item/:mid/comment  (or POST /api/item/:mid with no verb)
    // Body: { body, title? }
    private function createComment(string $parentMid): void
    {
        $ob_hash = Auth::requireLoggedInJson();
        $body = Auth::$parsedBody;
        $content = trim($body['body'] ?? '');

        if (!$content) {
            json_return_and_die(['error' => 'body is required']);
        }

        $item_normal = item_normal();

        // Resolve parent
        $parent = $this->resolveItem($parentMid, $ob_hash);
        if (!$parent) {
            json_return_and_die(['error' => 'Parent item not found or permission denied']);
        }

        // resolveItem only proves the observer can *view* the parent. Commenting
        // is a separate permission — enforce the channel's comment policy /
        // post_comments grant and closed-comment state, as core Item.php does.
        if (!can_comment_on_post($ob_hash, $parent)) {
            json_return_and_die(['error' => 'Commenting is not permitted on this post']);
        }

        $profileUid = intval($parent['uid']);
        $mimetype   = $body['mimetype'] ?? 'text/bbcode';

        $postTags    = [];
        $attachments = [];

        if ($mimetype === 'text/bbcode') {
            require_once('include/text.php');

            $content = cleanup_bbcode($content);

            // Linkify @mentions, #tags, !groups. Unlike top-level posts the
            // resulting tags never widen the thread ACL (core passes the
            // parent item to set_linkified_perms, which makes it a no-op), so
            // only the term records are collected here.
            $results = linkify_tags($content, $profileUid);
            if ($results) {
                foreach ($results as $result) {
                    $s = $result['success'];
                    if ($s['replaced']) {
                        $postTags[] = [
                            'uid'   => $profileUid,
                            'ttype' => $s['termtype'],
                            'otype' => TERM_OBJ_POST,
                            'term'  => $s['term'],
                            'url'   => $s['url'],
                        ];
                    }
                }
            }

            // Sync file/photo ACL to the thread's ACL so recipients can open them
            fix_attached_permissions($profileUid, $content,
                $parent['allow_cid'], $parent['allow_gid'],
                $parent['deny_cid'], $parent['deny_gid']);

            // Extract [attachment] tags → attach array, strip them from body
            if (preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/', $content, $match)) {
                require_once('include/attach.php');
                foreach ($match[2] as $i => $mtch) {
                    $hash = substr($mtch, 0, strpos($mtch, ','));
                    $rev  = intval(substr($mtch, strpos($mtch, ',')));
                    $r    = attach_by_hash_nodata($hash, $ob_hash, $rev);
                    if ($r['success']) {
                        $attachments[] = [
                            'href'     => z_root() . '/attach/' . $r['data']['hash'],
                            'length'   => $r['data']['filesize'],
                            'type'     => $r['data']['filetype'],
                            'title'    => urlencode($r['data']['filename']),
                            'revision' => $r['data']['revision'],
                        ];
                    }
                    $content = str_replace($match[1][$i], '', $content);
                }
            }

            $content = $this->expandShareTags($content);
            $content = $this->expandCardTags($content);

            $postTags = array_merge($postTags, self::buildEmojiTerms($profileUid, $content));
        }

        // Inherit ACL and privacy from parent
        $datarray = self::buildItemArray(
            profileUid: $profileUid,
            content: $content,
            title: trim($body['title'] ?? ''),
            mimetype: $mimetype,
            acl: [
                'allow_cid' => $parent['allow_cid'],
                'allow_gid' => $parent['allow_gid'],
                'deny_cid' => $parent['deny_cid'],
                'deny_gid' => $parent['deny_gid'],
            ],
            isWall: intval($parent['item_wall']) === 1,
            parent: $parent,
            term: $postTags,
            attach: $attachments,
        );

        call_hooks('post_local', $datarray);

        if (!empty($datarray['cancel'])) {
            json_return_and_die(['error' => 'Comment cancelled']);
        }

        $post = item_store($datarray);

        if (!$post['success']) {
            json_return_and_die(['error' => 'Failed to post comment']);
        }

        $datarray['id'] = $post['item_id'];
        call_hooks('post_local_end', $datarray);

        Master::Summon(['Notifier', 'comment-new', $post['item_id']]);

        json_return_and_die([
            'success' => true,
            'iid' => $post['item_id'],
            'mid' => $datarray['mid'],
            'uuid' => $datarray['uuid'],
        ]);
    }

    // POST /api/item/:mid/like|dislike|repeat
    // Toggles: sends the reaction if not present, drops it if already present.
    // Returns: { success, state: "added"|"removed", like_count, ... }
    private function toggleReaction(string $mid, string $activityVerb): void
    {
        $ob_hash = Auth::requireLoggedIn();
        $this->requireCsrf();

        $item_normal = item_normal();

        $target = $this->resolveItem($mid, $ob_hash);
        if (!$target) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $verbEsc = dbesc($activityVerb);
        $targetMid = dbesc($target['mid']);
        $obHashEsc = dbesc($ob_hash);
        $targetUid = intval($target['uid']);

        // Check for existing reaction on this same item copy
        $existing = dbq("SELECT id FROM item
                         WHERE uid = $targetUid
                           AND verb = '$verbEsc'
                           AND thr_parent = '$targetMid'
                           AND author_xchan = '$obHashEsc'
                           AND item_deleted = 0
                           $item_normal
                         LIMIT 1");

        if ($existing) {
            // Undo
            drop_item($existing[0]['id'], DROPITEM_PHASE1);
            Master::Summon(['Notifier', 'drop', $existing[0]['id']]);
            $state = 'removed';
        } else {
            // Add reaction — construct a minimal reaction item
            $uuid = item_message_id();
            $reactionMid = z_root() . '/item/' . $uuid;
            $now = datetime_convert();

            $datarray = [
                'aid' => intval($target['aid']),
                'uid' => $targetUid,
                'uuid' => $uuid,
                'mid' => $reactionMid,
                'parent_mid' => $target['mid'],
                'thr_parent' => $target['mid'],
                'owner_xchan' => $target['owner_xchan'],
                'author_xchan' => $ob_hash,
                'created' => $now,
                'edited' => $now,
                'commented' => $now,
                'received' => $now,
                'changed' => $now,
                'verb' => $activityVerb,
                'obj_type' => 'Activity',
                'body' => '',
                'title' => '',
                'mimetype' => 'text/bbcode',
                'allow_cid' => $target['allow_cid'],
                'allow_gid' => $target['allow_gid'],
                'deny_cid' => $target['deny_cid'],
                'deny_gid' => $target['deny_gid'],
                'item_private' => intval($target['item_private']),
                'item_wall' => intval($target['item_wall']),
                'item_origin' => 1,
                'item_thread_top' => 0,
                'item_notshown' => 1,
                'plink' => $reactionMid,
                'route' => $target['route'] ?? '',
            ];

            $post = item_store($datarray);
            if (!$post['success']) {
                json_return_and_die(['error' => 'Reaction failed']);
            }
            Master::Summon(['Notifier', 'like', $post['item_id']]);
            $state = 'added';
        }

        // Return fresh counts
        $counts = $this->fetchReactionCounts($target['mid']);
        json_return_and_die(array_merge(['success' => true, 'state' => $state], $counts));
    }

    // POST /api/item/:mid/accept|reject|tentativeaccept
    // Exclusive RSVP toggle: removes conflicting RSVP verbs, toggles the chosen one.
    private function toggleRsvpReaction(string $mid, string $activityVerb): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();
        $channel = App::get_channel();
        $ob_hash = $channel['channel_hash'];
        $item_normal = item_normal();

        $target = $this->resolveItem($mid, $ob_hash);
        if (!$target) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $obHashEsc = dbesc($ob_hash);
        $targetMid = dbesc($target['mid']);

        // Find any existing RSVP from this viewer on this item
        $existing = dbq("SELECT id, verb FROM item
                         WHERE uid = $uid
                           AND verb IN ('Accept','Reject','TentativeAccept')
                           AND thr_parent = '$targetMid'
                           AND author_xchan = '$obHashEsc'
                           AND item_deleted = 0
                           $item_normal
                         LIMIT 1");

        $state = 'added';

        if ($existing) {
            // Always remove the old RSVP first
            drop_item($existing[0]['id'], DROPITEM_PHASE1);
            Master::Summon(['Notifier', 'drop', $existing[0]['id']]);

            // If same verb, this is a toggle-off
            if ($existing[0]['verb'] === $activityVerb) {
                $state = 'removed';
                $counts = $this->fetchReactionCounts($target['mid']);
                json_return_and_die(array_merge(['success' => true, 'state' => $state], $counts));
            }
        }

        // Add the new RSVP reaction
        $uuid        = item_message_id();
        $reactionMid = z_root() . '/item/' . $uuid;
        $now         = datetime_convert();

        $datarray = [
            'aid'            => $channel['channel_account_id'],
            'uid'            => intval($target['uid']),
            'uuid'           => $uuid,
            'mid'            => $reactionMid,
            'parent_mid'     => $target['mid'],
            'thr_parent'     => $target['mid'],
            'owner_xchan'    => $target['owner_xchan'],
            'author_xchan'   => $ob_hash,
            'created'        => $now,
            'edited'         => $now,
            'commented'      => $now,
            'received'       => $now,
            'changed'        => $now,
            'verb'           => $activityVerb,
            'obj_type'       => 'Activity',
            'body'           => '',
            'title'          => '',
            'mimetype'       => 'text/bbcode',
            'allow_cid'      => $target['allow_cid'],
            'allow_gid'      => $target['allow_gid'],
            'deny_cid'       => $target['deny_cid'],
            'deny_gid'       => $target['deny_gid'],
            'item_private'   => intval($target['item_private']),
            'item_wall'      => intval($target['item_wall']),
            'item_origin'    => 1,
            'item_thread_top'=> 0,
            'item_notshown'  => 1,
            'plink'          => $reactionMid,
            'route'          => $target['route'] ?? '',
        ];

        $post = item_store($datarray);
        if (!$post['success']) {
            json_return_and_die(['error' => 'RSVP reaction failed']);
        }
        Master::Summon(['Notifier', 'like', $post['item_id']]);

        if (in_array($activityVerb, ['Accept', 'TentativeAccept']) && $target['obj_type'] === 'Event') {
            event_addtocal($target['id'], $uid);
        }

        $counts = $this->fetchReactionCounts($target['mid']);
        json_return_and_die(array_merge(['success' => true, 'state' => $state], $counts));
    }

    // POST /api/item/:mid/addtocal
    // Imports an Event item into the viewer's own calendar without recording
    // an RSVP reaction (mirrors the Accept/TentativeAccept side effect in
    // toggleRsvpReaction() above, callable directly from the stream's "more
    // actions" menu).
    private function addToCalendar(string $mid): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();
        $channel = App::get_channel();
        $ob_hash = $channel['channel_hash'];

        $target = $this->resolveItem($mid, $ob_hash);
        if (!$target) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }
        if ($target['obj_type'] !== 'Event') {
            json_return_and_die(['error' => 'Item is not an event']);
        }

        event_addtocal($target['id'], $uid);

        json_return_and_die(['success' => true]);
    }

    // POST /api/item/:mid/star
    // Toggles the starred flag on the item (local only — not federated)
    private function toggleStar(string $mid): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();
        $item_normal = item_normal();
        $midEsc = dbesc($mid);

        $item = dbq("SELECT id, item_starred FROM item
                     WHERE mid = '$midEsc' AND uid = $uid
                     $item_normal LIMIT 1");

        if (!$item) {
            json_return_and_die(['error' => 'Item not found']);
        }

        $newState = intval($item[0]['item_starred']) ? 0 : 1;
        $iid = intval($item[0]['id']);

        q('UPDATE item SET item_starred = %d WHERE id = %d AND uid = %d',
            $newState, $iid, $uid);

        json_return_and_die(['success' => true, 'starred' => (bool) $newState]);
    }

    // POST /api/item/:mid/pin
    // Toggles pinned state of a top-level, non-private wall post owned by the
    // local channel. Core parity (Zotlabs/Module/Pin.php): pconfig-backed
    // ('pinned' cat, ITEM_TYPE_POST key), synced to clones via Libsync. Unlike
    // core, membership in the pinned array is toggled (add/remove) rather than
    // always replaced — this SPA allows pinning more than one post at a time.
    private function togglePin(string $mid): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();

        if (str_starts_with($mid, 'b64.')) {
            $mid = unpack_link_id($mid);
        }
        $col = (str_contains($mid, '/') || str_contains($mid, ':')) ? 'mid' : 'uuid';
        $midEsc = dbesc($mid);

        $item = dbq("SELECT id, uuid FROM item
                     WHERE item.$col = '$midEsc' AND item.uid = $uid
                       AND item.id = item.parent
                       AND item.item_private = 0
                       AND item.item_wall = 1
                       AND item.item_deleted = 0
                     LIMIT 1");

        if (!$item) {
            json_return_and_die(['error' => 'Item not found, not eligible, or permission denied']);
        }

        $midb64 = $item[0]['uuid'];
        $pinned = get_pconfig($uid, 'pinned', ITEM_TYPE_POST, []);
        $pinned = is_array($pinned) ? $pinned : [];
        $isPinned = in_array($midb64, $pinned, true);

        $pinned = $isPinned
            ? array_values(array_diff($pinned, [$midb64]))
            : [...$pinned, $midb64];

        set_pconfig($uid, 'pinned', ITEM_TYPE_POST, $pinned);

        Libsync::build_sync_packet($uid, ['config']);

        json_return_and_die(['success' => true, 'pinned' => !$isPinned]);
    }

    // POST /api/item/:mid/follow | /api/item/:mid/unfollow
    // Mirrors core Mod_Subthread: records a Follow (sub) or Ignore (unsub)
    // activity authored by the viewer on the thread top of their own copy of
    // the thread. The activity is local-only (no delivery), and the latest
    // Follow/Ignore wins — the same convention the stream queries and the
    // pf (followed threads) filter rely on. Items that exist only in the sys
    // channel (pubstream) are copied into the viewer's channel first, exactly
    // like core's copy_of_pubitem() fallback.
    private function toggleThreadFollow(string $mid, bool $follow): void
    {
        require_once('include/channel.php');

        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid         = local_channel();
        $channel     = App::get_channel();
        $observer    = App::get_observer();
        $ob_hash     = $channel['channel_hash'];
        $item_normal = item_normal();

        $target = $this->resolveItem($mid, $ob_hash);
        if (!$target) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        // The follow state lives on the viewer's copy of the thread.
        // resolveItem() already prefers it; anything else means the viewer
        // has no copy — pull pubstream items in, refuse the rest.
        if (intval($target['uid']) !== $uid) {
            $sys = get_sys_channel();
            if (intval($target['uid']) === intval($sys['channel_id'])) {
                $copy = copy_of_pubitem($channel, $target['mid']);
                if (!$copy) {
                    json_return_and_die(['error' => 'Unable to copy item to your stream']);
                }
                $target = $copy;
            } else {
                json_return_and_die(['error' => 'This conversation is not in your stream']);
            }
        }

        // Follow state always attaches to the thread top (like core subthread)
        if (!intval($target['item_thread_top'])) {
            $pid = intval($target['parent']);
            $r = dbq("SELECT * FROM item WHERE id = $pid $item_normal LIMIT 1");
            if (!$r) {
                json_return_and_die(['error' => 'Thread not found']);
            }
            $target = $r[0];
        }

        // No-op when the latest Follow/Ignore already matches the request
        $tid    = intval($target['id']);
        $obsEsc = dbesc($ob_hash);
        $cur = dbq("SELECT verb FROM item
                    WHERE parent = $tid
                      AND author_xchan = '$obsEsc'
                      AND verb IN ('Follow', 'Ignore')
                      AND item_deleted = 0
                    ORDER BY created DESC LIMIT 1");
        if (!empty($cur)) {
            $currently = $cur[0]['verb'] === 'Follow';
        } else {
            // No explicit Follow/Ignore yet — a comment already makes core
            // notify() treat the viewer as following, so Unfollow must still
            // record an explicit Ignore instead of no-op'ing here.
            $participated = dbq("SELECT id FROM item
                        WHERE parent = $tid
                          AND author_xchan = '$obsEsc'
                          AND verb NOT IN ('Follow', 'Ignore')
                          AND item_deleted = 0
                        LIMIT 1");
            $currently = !empty($participated);
        }
        if ($currently === $follow) {
            json_return_and_die(['success' => true, 'following' => $follow]);
        }

        $author = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
            dbesc($target['author_xchan']));
        if (!$author) {
            json_return_and_die(['error' => 'Item author not found']);
        }

        $uuid      = item_message_id();
        $post_type = (($target['resource_type'] ?? '') === 'photo') ? t('photo') : t('status');
        $ulink     = '[zrl=' . $author[0]['xchan_url'] . ']' . $author[0]['xchan_name'] . '[/zrl]';
        $alink     = '[zrl=' . $observer['xchan_url'] . ']' . $observer['xchan_name'] . '[/zrl]';
        $plink     = '[zrl=' . z_root() . '/display/' . $target['uuid'] . ']' . $post_type . '[/zrl]';
        $bodyverb  = $follow
            ? t('%1$s is following %2$s\'s %3$s')
            : t('%1$s stopped following %2$s\'s %3$s');

        $arr = [
            'uuid'          => $uuid,
            'mid'           => z_root() . '/item/' . $uuid,
            'aid'           => $target['aid'],
            'uid'           => intval($target['uid']),
            'parent'        => $tid,
            'parent_mid'    => $target['mid'],
            'thr_parent'    => $target['mid'],
            'owner_xchan'   => $target['owner_xchan'],
            'author_xchan'  => $ob_hash,
            'item_origin'   => 1,
            'item_notshown' => 1,
            'item_wall'     => intval($target['item_wall']),
            'verb'          => $follow ? 'Follow' : 'Ignore',
            'obj_type'      => (($target['resource_type'] ?? '') === 'photo') ? 'Image' : 'Note',
            'body'          => sprintf($bodyverb, $alink, $ulink, $plink),
            'allow_cid'     => $target['allow_cid'],
            'allow_gid'     => $target['allow_gid'],
            'deny_cid'      => $target['deny_cid'],
            'deny_gid'      => $target['deny_gid'],
        ];

        $post = item_store($arr, false, false, false);
        if (empty($post['item_id'])) {
            json_return_and_die(['error' => 'Failed to store activity']);
        }

        $arr['id'] = $post['item_id'];
        call_hooks('post_local_end', $arr);

        json_return_and_die(['success' => true, 'following' => $follow]);
    }

    // POST /api/item/:mid/edit
    // JSON body: { body, title?, summary?, mimetype?, pagetitle?, category? }
    // Only the item owner can edit.
    //
    // `category` is authoritative when present: the edit composer round-trips
    // the item's existing categories, so sending "" means "remove them all".
    // Callers that don't send the key at all (the inline comment editor) leave
    // the item's categories untouched — see the term rebuild below.
    private function editItem(string $mid): void
    {
        $uid      = Auth::requireLocalJson();
        $content  = trim(Auth::$parsedBody['body']      ?? '');
        $title    = trim(Auth::$parsedBody['title']     ?? '');
        $summary  = trim(Auth::$parsedBody['summary']   ?? '');
        $mimetype = trim(Auth::$parsedBody['mimetype']  ?? 'text/bbcode');
        $slug     = trim(Auth::$parsedBody['pagetitle'] ?? '');
        $catsGiven = array_key_exists('category', Auth::$parsedBody);
        $category  = trim(Auth::$parsedBody['category'] ?? '');

        if (!$content) {
            Response::error(400, 'body is required');
        }

        $postTags = [];

        require_once('include/text.php');

        if ($mimetype === 'text/bbcode') {
            $content = cleanup_bbcode($content);

            // Rebuild mention/hashtag/group term records from the edited body.
            // Unlike a new post, editing never widens the thread ACL (same
            // reasoning as createComment()) — only term records are collected.
            $results = linkify_tags($content, $uid);
            if ($results) {
                foreach ($results as $result) {
                    $s = $result['success'];
                    if ($s['replaced']) {
                        $postTags[] = [
                            'ttype' => $s['termtype'],
                            'term'  => $s['term'],
                            'url'   => $s['url'],
                        ];
                    }
                }
            }

            $content = $this->expandShareTags($content);
            $content = $this->expandCardTags($content);

            $postTags = array_merge($postTags, self::buildEmojiTerms($uid, $content));
        } else {
            // editItem() writes directly to the item row rather than going
            // through item_store_update(), so it must apply the same
            // z_input_filter() sanitization/permission gate core's own
            // editor applies before storing (Zotlabs/Module/Item.php
            // $execflag pattern) — otherwise a client-supplied mimetype
            // like text/html would be stored and later rendered raw
            // (prepare_text() does no sanitization at display time for
            // text/html; it's a store-time-only guarantee).
            $content = z_input_filter($content, $mimetype, channel_codeallowed($uid));
        }

        // The frontend sends the short uuid (e.g. "abc123") not the full mid URL.
        // Use the right column: uuid for bare identifiers, mid for full URLs.
        $col    = (str_contains($mid, '/') || str_contains($mid, ':')) ? 'mid' : 'uuid';
        $midEsc = dbesc($mid);

        // Do NOT use item_normal() here — it restricts to item_type = ITEM_TYPE_POST (0),
        // which would exclude webpages, articles, wiki pages, etc.
        $item = dbq("SELECT * FROM item
                     WHERE $col = '$midEsc' AND uid = $uid
                     AND item_deleted = 0 LIMIT 1");

        if (!$item) {
            Response::error(404, 'Item not found or permission denied');
        }

        $iid = intval($item[0]['id']);
        $now = datetime_convert();

        // Files attached while editing arrive as [attachment] tags appended to
        // the body, exactly as in createPost(). Extract and strip them the same
        // way, then merge on top of the attachments the item already has —
        // those were stripped from the body at create time and live only in
        // item.attach, so they are not in the edited body to re-extract.
        $attachments = json_decode($item[0]['attach'] ?? '', true) ?: [];
        if ($mimetype === 'text/bbcode'
            && preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/', $content, $match)) {
            require_once('include/attach.php');
            $ob_hash = get_observer_hash();
            $seen    = array_column($attachments, 'href');
            foreach ($match[2] as $i => $mtch) {
                $hash = substr($mtch, 0, strpos($mtch, ','));
                $rev  = intval(substr($mtch, strpos($mtch, ',')));
                $r    = attach_by_hash_nodata($hash, $ob_hash, $rev);
                if ($r['success'] && !in_array(z_root() . '/attach/' . $r['data']['hash'], $seen, true)) {
                    $attachments[] = [
                        'href'     => z_root() . '/attach/' . $r['data']['hash'],
                        'length'   => $r['data']['filesize'],
                        'type'     => $r['data']['filetype'],
                        'title'    => urlencode($r['data']['filename']),
                        'revision' => $r['data']['revision'],
                    ];
                    $seen[] = z_root() . '/attach/' . $r['data']['hash'];
                }
                $content = str_replace($match[1][$i], '', $content);
            }
            // Newly attached files/photos inherit the post's existing ACL, so a
            // private file can't leak onto a public post (and vice versa).
            fix_attached_permissions($uid, $content,
                $item[0]['allow_cid'], $item[0]['allow_gid'],
                $item[0]['deny_cid'],  $item[0]['deny_gid']);
        }

        q("UPDATE item SET body = '%s', title = '%s', summary = '%s', mimetype = '%s',
                           attach = '%s', edited = '%s', changed = '%s'
           WHERE id = %d AND uid = %d",
            dbesc($content), dbesc($title), dbesc($summary), dbesc($mimetype),
            dbesc($attachments ? json_encode($attachments) : ''),
            dbesc($now), dbesc($now), $iid, $uid);

        // Rebuild term records to match the edited body (mentions, hashtags,
        // groups, emoji) — same delete+reinsert approach core's own
        // item_store_update() uses (include/items.php ~2400-2418), since this
        // handler updates the item row directly rather than going through it.
        // Categories → term records, same shape as createPost() so they
        // federate identically. Only rebuilt when the caller sent the key.
        if ($catsGiven && $category) {
            require_once('include/channel.php');
            $ownerChannel = channelx_by_n($uid);
            foreach (array_filter(array_map('trim', explode(',', $category))) as $cat) {
                $postTags[] = [
                    'uid'   => $uid,
                    'ttype' => TERM_CATEGORY,
                    'otype' => TERM_OBJ_POST,
                    'term'  => $cat,
                    'url'   => channel_url($ownerChannel) . '?cat=' . urlencode($cat),
                ];
            }
        }

        // Terms not derived from the body must survive the rebuild. Core's
        // editor keeps them by re-submitting $_POST['category'] and re-reading
        // TERM_UNKNOWN/TERM_FILE/TERM_COMMUNITYTAG off the original post
        // (Zotlabs/Module/Item.php ~180 and ~776). Categories are preserved the
        // same way *unless* the caller supplied the key, in which case the
        // payload is authoritative and stale category rows must go.
        $keep = [TERM_UNKNOWN, TERM_PCATEGORY, TERM_FILE, TERM_COMMUNITYTAG];
        if (!$catsGiven) $keep[] = TERM_CATEGORY;
        q("DELETE FROM term WHERE oid = %d AND otype = %d AND ttype NOT IN (" .
            implode(',', array_map('intval', $keep)) . ")",
            $iid, intval(TERM_OBJ_POST));
        foreach ($postTags as $t) {
            q("INSERT INTO term (uid, oid, otype, ttype, term, url, imgurl)
                VALUES (%d, %d, %d, %d, '%s', '%s', '%s')",
                intval($uid), $iid, intval(TERM_OBJ_POST), intval($t['ttype']),
                dbesc($t['term']), dbesc($t['url']), dbesc($t['imgurl'] ?? ''));
        }

        // Update the WEBPAGE slug (stored in iconfig) if one was provided
        if ($slug) {
            q("UPDATE iconfig SET v = '%s' WHERE iid = %d AND cat = 'system' AND k = 'WEBPAGE'",
                dbesc($slug), $iid);
        }

        // Local-only posts (see createPost()) never federate — including edits,
        // which would otherwise be the first thing ever delivered for them.
        if (!get_iconfig($iid, 'spa', 'local_only')) {
            Master::Summon(['Notifier', 'edit_post', $iid]);
        }

        Response::send(['success' => true]);
    }

    // POST /api/item/:mid/delete
    // Owner or admin only. Federated drop.
    private function deleteItem(string $mid): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();
        $ob_hash = get_observer_hash();

        // Prefer the caller's own copy (needed below to correctly tell whether
        // this is really their own stream copy); falls back to any accessible
        // copy for the true-author/owner/admin case.
        $i = $this->resolveItem($mid, $ob_hash);

        if (!$i) {
            json_return_and_die(['error' => 'Item not found']);
        }

        // $local_delete: this row lives under the caller's own uid (their own
        // stream/wall copy) — lets them remove it from their own feed only.
        // $can_delete: the caller actually authored/owns/sourced this content
        // (or is a site admin deleting content that originated here) — lets
        // them perform a real, federated delete. Mirrors core's
        // Zotlabs/Module/Item.php::get() (/item/drop/:id).
        $local_delete = ($uid && $uid == $i['uid']);

        $can_delete = (
            $ob_hash && in_array($ob_hash, [$i['author_xchan'], $i['owner_xchan'], $i['source_xchan']], true)
        );

        if (is_site_admin()) {
            $local_delete = true;
            if (intval($i['item_origin'])) {
                $can_delete = true;
            }
        }

        if (!($can_delete || $local_delete)) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        if ($local_delete && !$can_delete) {
            // Local-only removal: just this one row (drop_item()'s internal
            // cascade also removes same-uid child comments under it). No sync
            // packet, no tag_deliver, no Notifier summon — nothing federates,
            // other copies of this post elsewhere are untouched.
            drop_item(intval($i['id']));
            json_return_and_die(['success' => true]);
        }

        // Drop all local copies (same mid stored under different channel uids)
        $globalMidEsc = dbesc($i['mid']);
        $all_copies = dbq("SELECT * FROM item WHERE mid = '$globalMidEsc' AND item_deleted = 0");

        // Prefer the wall copy for federation; fall back to first found
        $primary = $i;
        foreach ($all_copies as $copy) {
            if (intval($copy['item_wall'])) {
                $primary = $copy;
                break;
            }
        }

        foreach ($all_copies as $copy) {
            drop_item($copy['id'], DROPITEM_PHASE1);
        }

        $r = q('SELECT * FROM item WHERE id = %d', intval($primary['id']));
        if ($r) {
            xchan_query($r);
            $sync = fetch_post_tags($r);
            Libsync::build_sync_packet($primary['uid'], ['item' => [encode_item($sync[0], true)]]);
        }

        tag_deliver($primary['uid'], $primary['id']);

        // A local-only post was never delivered to its ACL'd recipients in the
        // first place — summoning a 'drop' here would deliver a Delete to
        // people who never received the Create, leaking that a hidden post
        // existed.
        $wasLocalOnly = (bool) get_iconfig($primary['id'], 'spa', 'local_only');
        if (!$wasLocalOnly && (intval($primary['item_wall']) || $primary['mid'] !== $primary['parent_mid'])) {
            Master::Summon(['Notifier', 'drop', $primary['id']]);
        }

        json_return_and_die(['success' => true]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    // Resolve a mid (or uuid) to a readable item row, permission-checked.
    // Accepts full mid (zot6 URL), short uuid, or b64-encoded mid.
    // GET /api/item/:mid/folders
    // Returns the folder names this post is currently filed under for the local user.
    private function getItemFolders(string $mid): void
    {
        Auth::RequireLocalGet();
        $uid = local_channel();

        $item = $this->resolveItem($mid, get_observer_hash());
        if (!$item || intval($item['uid']) !== $uid) {
            json_return_and_die(['data' => []]);
        }

        $rows = q(
            "SELECT term FROM term WHERE uid = %d AND oid = %d AND ttype = %d ORDER BY term ASC",
            intval($uid), intval($item['id']), intval(TERM_FILE)
        );

        json_return_and_die(['data' => $rows ? array_column($rows, 'term') : []]);
    }

    // POST /api/item/:mid/saveto
    // Body: { "name": "folder name" }            → add to folder
    // Body: { "name": "folder name", "remove": true } → remove from folder
    private function saveToFolder(string $mid): void
    {
        Auth::requireLocalJson();
        $uid = local_channel();

        $name = trim(Auth::$parsedBody['name'] ?? '');
        $remove = !empty(Auth::$parsedBody['remove']);

        if (!$name) {
            \Utsukta\SpaCore\Api\Response::error(400, 'name required');
        }

        $item = $this->resolveItem($mid, get_observer_hash());
        if (!$item || intval($item['uid']) !== $uid) {
            \Utsukta\SpaCore\Api\Response::error(403, 'Item not found in your stream');
        }

        $item_id = intval($item['id']);
        $parent_id = intval($item['parent']);

        if ($remove) {
            q("DELETE FROM term WHERE uid = %d AND oid = %d AND ttype = %d AND term = '%s'",
                intval($uid), $item_id, intval(TERM_FILE), dbesc($name));
            q("UPDATE item SET item_retained = 0, changed = '%s' WHERE id = %d AND uid = %d",
                dbesc(datetime_convert()), $item_id, intval($uid));
        } else {
            store_item_tag($uid, $item_id, TERM_OBJ_POST, TERM_FILE, $name, '');
            q("UPDATE item SET item_retained = 1, changed = '%s' WHERE id = %d AND uid = %d",
                dbesc(datetime_convert()), $parent_id, intval($uid));
        }

        $rows = q(
            "SELECT term FROM term WHERE uid = %d AND oid = %d AND ttype = %d ORDER BY term ASC",
            intval($uid), $item_id, intval(TERM_FILE)
        );

        json_return_and_die(['data' => ['folders' => $rows ? array_column($rows, 'term') : []]]);
    }

    // GET /api/item/:mid/delivery
    // Returns delivery report entries for a post authored by the logged-in user.
    private function getDeliveryReport(string $mid): void
    {
        Auth::requireLocalGet();
        $channel = \App::get_channel();
        $channelHash = $channel['channel_hash'];

        $item = $this->resolveItem($mid, $channelHash);
        if (!$item) {
            Response::error(404, 'Item not found or permission denied');
        }

        $isAuthor    = $item['author_xchan'] === $channelHash;
        $isWallOwner = $item['owner_xchan'] === $channelHash && intval($item['item_wall']) === 1;
        if (!$isAuthor && !$isWallOwner) {
            Response::error(403, 'Permission denied');
        }

        $itemMid     = dbesc($item['mid']);
        $activityMid = dbesc(str_replace('/item/', '/activity/', $item['mid']));
        $hashEsc     = dbesc($channelHash);

        $rows = dbq("SELECT dreport_name, dreport_recip, dreport_result, dreport_time
                     FROM dreport
                     WHERE dreport_xchan = '$hashEsc'
                       AND (dreport_mid = '$itemMid' OR dreport_mid = '$activityMid')
                     ORDER BY dreport_time ASC");

        $entries = [];
        foreach ($rows ?: [] as $r) {
            $entries[] = [
                'name'   => $r['dreport_name'] ?: $r['dreport_recip'],
                'result' => $r['dreport_result'],
                'time'   => $r['dreport_time'],
            ];
        }

        Response::send($entries);
    }

    private function resolveItem(string $mid, string $ob_hash): ?array
    {
        // Do NOT use item_normal() here — it restricts to item_type = ITEM_TYPE_POST (0),
        // which would exclude webpages, articles, wiki pages, etc. (same reasoning as
        // editItem()/deleteItem() below).

        // b64-encoded mid
        if (str_starts_with($mid, 'b64.')) {
            $mid = unpack_link_id($mid);
        }

        // Try as uuid first (shorter, common in frontend URLs)
        $col = (str_contains($mid, '/') || str_contains($mid, ':'))
            ? 'mid'
            : 'uuid';

        $midEsc = dbesc($mid);

        // Prefer a copy the local user owns
        if (local_channel()) {
            $uid = local_channel();
            $r = dbq("SELECT * FROM item
                      WHERE item.$col = '$midEsc'
                        AND item.uid = $uid
                        AND item.item_deleted = 0
                      LIMIT 1");
            if ($r)
                return $r[0];
        }

        // Fall back to any publicly accessible copy
        $permission_sql = item_permissions_sql(0, $ob_hash);
        $r = dbq("SELECT * FROM item
                  WHERE item.$col = '$midEsc'
                    AND item.item_deleted = 0
                    $permission_sql
                  ORDER BY item_wall DESC
                  LIMIT 1");

        return $r ? $r[0] : null;
    }

    // Scan body text for :shortcode: emoji recognized by get_emojis() and build
    // TERM_EMOJI term records so they federate as an AP Emoji tag (core mirror:
    // Zotlabs/Module/Item.php ~line 729), instead of surviving only as dead
    // shortcode text for remote instances that don't already know them.
    private static function buildEmojiTerms(int $profileUid, string $content): array
    {
        $terms = [];

        if (preg_match_all('/(\:(\w|\+|\-)+\:)(?=|[\!\.\?]|$)/', $content, $match)) {
            $emojis = get_emojis();
            foreach ($match[0] as $mtch) {
                $shortname = trim($mtch, ':');

                if (!isset($emojis[$shortname])) {
                    continue;
                }

                $emoji = $emojis[$shortname];

                $terms[] = [
                    'uid'    => $profileUid,
                    'ttype'  => TERM_EMOJI,
                    'otype'  => TERM_OBJ_POST,
                    'term'   => trim($mtch),
                    'url'    => z_root() . '/emoji/' . $shortname,
                    'imgurl' => z_root() . '/' . $emoji['filepath'],
                ];
            }
        }

        return $terms;
    }

    // Build a minimal item datarray for item_store().
    // Handles both top-level posts and comments.
    private static function buildItemArray(
        int $profileUid,
        string $content,
        string $title,
        string $mimetype,
        array $acl,
        bool $isWall,
        ?array $parent = null,
        array $term = [],
        array $attach = [],
    ): array {
        $channel = App::get_channel();
        $observer = App::get_observer();
        $uuid = item_message_id();
        $mid = z_root() . '/item/' . $uuid;
        $now = datetime_convert();
        $isComment = $parent !== null;

        $parentMid = $isComment ? $parent['mid'] : $mid;
        $thrParent = $isComment ? $parent['mid'] : $mid;
        $ownerHash = $isComment ? $parent['owner_xchan'] : $channel['channel_hash'];
        // Comments inherit the thread's privacy verbatim (core Item::post) —
        // deriving it from the ACL would downgrade a DM (private=2) to 1.
        $private = $isComment
            ? intval($parent['item_private'])
            : (!empty($acl['allow_cid']) || !empty($acl['allow_gid']) ? 1 : 0);
        // Comments are stored under the parent's owning account (App::get_channel()
        // is empty for a remote/OWA commenter, who has no local channel here).
        $aid = $isComment ? intval($parent['aid']) : intval($channel['channel_account_id'] ?? 0);

        return [
            'aid' => $aid,
            'uid' => $profileUid,
            'uuid' => $uuid,
            'mid' => $mid,
            'parent_mid' => $parentMid,
            'thr_parent' => $thrParent,
            'owner_xchan' => $ownerHash,
            'author_xchan' => $observer['xchan_hash'],
            'created' => $now,
            'edited' => $now,
            'commented' => $now,
            'received' => $now,
            'changed' => $now,
            'verb' => 'Create',
            'obj_type' => 'Note',
            'mimetype' => $mimetype,
            'title' => $title,
            'body' => $content,
            'term' => $term,
            'attach' => $attach,
            'allow_cid' => $acl['allow_cid'] ?? '',
            'allow_gid' => $acl['allow_gid'] ?? '',
            'deny_cid' => $acl['deny_cid'] ?? '',
            'deny_gid' => $acl['deny_gid'] ?? '',
            'item_wall' => $isWall ? 1 : 0,
            'item_origin' => 1,
            'item_thread_top' => $isComment ? 0 : 1,
            'item_unseen' => 0,
            'item_private' => $private,
            'plink' => $mid,
            'route' => $parent['route'] ?? '',
        ];
    }

    // Map a scope string to an ACL array
    private static function scopeToAcl(string $scope, int $profileUid): array
    {
        if ($scope === 'private') {
            $channel = App::get_channel();
            return [
                'allow_cid' => '<' . $channel['channel_hash'] . '>',
                'allow_gid' => '',
                'deny_cid' => '',
                'deny_gid' => '',
            ];
        }
        if ($scope === 'contacts') {
            // Use the channel's configured default ACL
            $r = q('SELECT * FROM channel WHERE channel_id = %d LIMIT 1', $profileUid);
            $acl = new \Zotlabs\Access\AccessList($r ? $r[0] : App::get_channel());
            $g = $acl->get();
            return [
                'allow_cid' => $g['allow_cid'],
                'allow_gid' => $g['allow_gid'],
                'deny_cid' => $g['deny_cid'],
                'deny_gid' => $g['deny_gid'],
            ];
        }
        // public
        return ['allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => ''];
    }

    // Find deleted items that are parents of the given formatted comments but
    // absent from the result set. Returns pre-formatted stubs so the frontend
    // can build a complete thread tree without gaps.
    private static function findDeletedParentStubs(array $comments, string $rootMid): array
    {
        if (empty($comments)) return [];

        $presentMids = array_column($comments, 'mid');
        $missing = [];
        foreach ($comments as $c) {
            $tp = $c['thr_parent'] ?? '';
            if ($tp && $tp !== $rootMid && !in_array($tp, $presentMids) && !in_array($tp, $missing)) {
                $missing[] = $tp;
            }
        }
        if (empty($missing)) return [];

        $inList  = implode("','", array_map('dbesc', $missing));
        $deleted = dbq("SELECT uuid, mid, parent_mid, thr_parent, created
                        FROM item
                        WHERE mid IN ('$inList') AND item_deleted = 1
                        ORDER BY created ASC");

        return array_map(fn($d) => [
            'uuid'             => $d['uuid'],
            'mid'              => $d['mid'],
            'parent_mid'       => $d['parent_mid'],
            'thr_parent'       => $d['thr_parent'],
            'created'          => $d['created'],
            'edited'           => $d['created'],
            'title'            => '',
            'body'             => '',
            'verb'             => 'Create',
            'obj_type'         => 'Note',
            'like_count'       => 0,
            'dislike_count'    => 0,
            'announce_count'   => 0,
            'comment_count'    => 0,
            'item_private'     => 0,
            'item_thread_top'  => 0,
            'item_unseen'      => 0,
            'iid'              => 0,
            'profile_uid'      => 0,
            'flags'            => ['deleted'],
            'author'           => ['name' => '', 'address' => '', 'url' => '', 'hash' => '', 'photo' => ['src' => '', 'mimetype' => '']],
            'permalink'        => '',
            'viewer_liked'     => false,
            'viewer_disliked'  => false,
            'viewer_repeated'  => false,
            'viewer_attending' => false,
            'viewer_declining' => false,
            'viewer_maybe'     => false,
            'viewer_following' => false,
            'can_comment'      => false,
        ], $deleted ?: []);
    }

    // Shared reaction count subqueries string
    private static function reactionSubqueries(): string
    {
        return ReactionCounts::subqueries();
    }

    // Fetch fresh counts after a toggle — avoids a full item re-fetch
    private function fetchReactionCounts(string $mid): array
    {
        $midEsc = dbesc($mid);
        $uid = intval(local_channel());
        $normal = ReactionCounts::normalFlags();
        $r = dbq("SELECT
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = $uid AND r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Like'              AND $normal) AS like_count,
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = $uid AND r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Dislike'           AND $normal) AS dislike_count,
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = $uid AND r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = '" . ACTIVITY_SHARE . "' AND $normal) AS announce_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Accept'            AND $normal) AS attend_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Reject'            AND $normal) AS decline_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'TentativeAccept'   AND $normal) AS maybe_count");

        return [
            'like_count'     => intval($r[0]['like_count'] ?? 0),
            'dislike_count'  => intval($r[0]['dislike_count'] ?? 0),
            'announce_count' => intval($r[0]['announce_count'] ?? 0),
            'attend_count'   => intval($r[0]['attend_count'] ?? 0),
            'decline_count'  => intval($r[0]['decline_count'] ?? 0),
            'maybe_count'    => intval($r[0]['maybe_count'] ?? 0),
        ];
    }

    // Shared item formatter — same shape as your existing network/channel items
    private static function formatItem(array $item, string $ob_hash): array
    {
        $liked = $disliked = $repeated = $attending = $declining = $maybe = false;
        if ($ob_hash && !empty($item['reaction_verbs'])) {
            foreach (explode('|', $item['reaction_verbs']) as $rv) {
                if (!str_contains($rv, ':'))
                    continue;
                [$v, $xchan] = explode(':', $rv, 2);
                if ($xchan !== $ob_hash)
                    continue;
                if ($v === 'Like')           $liked      = true;
                if ($v === 'Dislike')        $disliked   = true;
                if ($v === 'Announce')       $repeated   = true;
                if ($v === 'Accept')         $attending  = true;
                if ($v === 'Reject')         $declining  = true;
                if ($v === 'TentativeAccept') $maybe     = true;
            }
        }

        $owner = null;
        if (($item['owner_xchan'] ?? '') !== ($item['author_xchan'] ?? '') && !empty($item['owner'])) {
            $x = $item['owner'];
            $owner = [
                'name'    => Response::decodeEntities($x['xchan_name'] ?? ''),
                'address' => $x['xchan_addr']            ?? '',
                'url'     => $x['xchan_url']             ?? '',
                'hash'    => $x['xchan_hash']            ?? '',
                'photo'   => [
                    'src'      => $x['xchan_photo_m']        ?? '',
                    'mimetype' => $x['xchan_photo_mimetype'] ?? '',
                ],
            ];
        }

        $attachRaw = $item['attach'] ?? '';
        $root = z_root();
        $attach = array_map(function (array $a) use ($root): array {
            // Pre-fix rows may have been stored with 'url' instead of 'href'.
            if (!isset($a['href']) && isset($a['url'])) {
                $a['href'] = $a['url'];
            }
            if (isset($a['href']) && str_starts_with($a['href'], '/')) {
                $a['href'] = $root . $a['href'];
            }
            return $a;
        }, $attachRaw ? (json_decode($attachRaw, true) ?: []) : []);

        // Only top-level items can be pinned — skip the pconfig lookup for comments.
        $isPinned = false;
        if (intval($item['item_thread_top']) && !empty($item['uid']) && !empty($item['uuid'])) {
            $pinnedMidsRaw = get_pconfig(intval($item['uid']), 'pinned', ITEM_TYPE_POST, []);
            $pinnedMids    = array_map('unpack_link_id', is_array($pinnedMidsRaw) ? $pinnedMidsRaw : []);
            $isPinned      = in_array($item['uuid'], $pinnedMids, true);
        }

        return [
            'uuid' => $item['uuid'],
            'mid' => $item['mid'],
            'parent_mid' => $item['parent_mid'],
            'thr_parent' => $item['thr_parent'],
            'message_top' => intval($item['item_thread_top'])
                ? $item['mid']
                : ($item['thr_parent'] ?? $item['mid']),
            'created' => $item['created'],
            'edited' => $item['edited'],
            'commented' => $item['commented'] ?? $item['created'],
            'title' => $item['title'],
            'body' => $item['body'],
            'verb' => $item['verb'],
            'obj_type' => $item['obj_type'],
            'like_count' => intval($item['like_count'] ?? 0),
            'dislike_count' => intval($item['dislike_count'] ?? 0),
            'announce_count' => intval($item['announce_count'] ?? 0),
            'comment_count' => intval($item['comment_count'] ?? 0),
            'item_private' => intval($item['item_private']),
            'item_thread_top' => intval($item['item_thread_top']),
            'item_unseen' => intval($item['item_unseen'] ?? 0),
            'iid' => intval($item['id']),
            'profile_uid' => intval($item['uid']),
            'flags' => array_values(array_filter([
                intval($item['item_thread_top']) ? 'thread_parent' : null,
                intval($item['item_private']) ? 'private' : null,
                intval($item['item_private']) === 2 ? 'direct_message' : null,
                intval($item['item_starred']) ? 'starred' : null,
                $isPinned ? 'pinned' : null,
                intval($item['item_unseen']) ? 'unseen' : null,
            ])),
            'author' => [
                'name'    => Response::decodeEntities($item['author']['xchan_name'] ?? ''),
                'address' => $item['author']['xchan_addr']            ?? '',
                'url'     => $item['author']['xchan_url']             ?? '',
                'hash'    => $item['author']['xchan_hash']            ?? '',
                'network' => $item['author']['xchan_network']         ?? '',
                'photo'   => [
                    'src'      => $item['author']['xchan_photo_m']        ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'owner'            => $owner,
            'permalink'        => $item['plink'] ?? '',
            'viewer_liked'     => $liked,
            'viewer_disliked'  => $disliked,
            'viewer_repeated'  => $repeated,
            'viewer_attending' => $attending,
            'viewer_declining' => $declining,
            'viewer_maybe'     => $maybe,
            'viewer_following' => (bool)($item['viewer_following'] ?? false),
            // Same check core uses to decide whether to render a comment box
            // (comment_policy, comments_closed, nocomment, owner perms).
            'can_comment'      => (bool) can_comment_on_post($ob_hash, $item),
            'attach'           => $attach,
            'poll'             => self::extractPoll($item, $ob_hash),
            // Mirrors Concerns\FormatsItems::formatItem() — this handler has its own
            // copy rather than using that trait, so the field has to be added twice
            // or the single-item view would show no categories while the streams do.
            // Callers hydrate $item['term'] via fetch_post_tags(), so no extra query.
            'categories'       => array_values(array_column(
                get_terms_oftype($item['term'] ?? [], TERM_CATEGORY), 'term')),
        ];
    }

    private static function extractPoll(array $item, string $observer_xchan): ?array
    {
        if (($item['obj_type'] ?? '') !== 'Question') return null;
        $raw = $item['obj'] ?? '';
        if (!$raw) return null;

        $obj = is_array($raw) ? $raw : json_decode($raw, true);
        if (!$obj || ($obj['type'] ?? '') !== 'Question') return null;

        $multiple = false;
        $choices  = $obj['oneOf'] ?? null;
        if (empty($choices)) {
            $choices  = $obj['anyOf'] ?? [];
            $multiple = true;
        }

        $options = [];
        foreach ($choices as $opt) {
            $options[] = [
                'name'  => htmlspecialchars_decode($opt['name'] ?? '', ENT_QUOTES | ENT_HTML5),
                'votes' => intval($opt['replies']['totalItems'] ?? 0),
            ];
        }

        $viewer_votes = [];
        if ($observer_xchan && !empty($item['id'])) {
            $iid   = intval($item['id']);
            $obEsc = dbesc($observer_xchan);
            $rows  = dbq("SELECT title FROM item
                          WHERE parent = $iid
                            AND author_xchan = '$obEsc'
                            AND obj_type = 'Answer'
                            AND item_deleted = 0");
            if ($rows) {
                $viewer_votes = array_column($rows, 'title');
            }
        }

        return [
            'multiple'     => $multiple,
            'end_time'     => $obj['endTime'] ?? null,
            'closed'       => $obj['closed']  ?? null,
            'options'      => $options,
            'viewer_votes' => $viewer_votes,
        ];
    }

    // POST /api/item/:mid/vote
    // Body: { "answer": "Option name" } or { "answer": ["Option A", "Option B"] } for multi-choice
    private function voteOnPoll(string $mid): void
    {
        Auth::requireLocalJson();

        $uid     = local_channel();
        $channel = App::get_channel();
        $ob_hash = $channel['channel_hash'];

        $answer = Auth::$parsedBody['answer'] ?? null;

        if ($answer === null) {
            json_return_and_die(['error' => 'answer is required']);
        }

        $poll = $this->resolveItem($mid, $ob_hash);
        if (!$poll || ($poll['obj_type'] ?? '') !== 'Question') {
            json_return_and_die(['error' => 'Poll not found']);
        }

        $iid   = intval($poll['id']);
        $obEsc = dbesc($ob_hash);

        $existing = dbq("SELECT id FROM item
                         WHERE parent = $iid
                           AND author_xchan = '$obEsc'
                           AND obj_type = 'Answer'
                           AND item_deleted = 0
                         LIMIT 1");
        if ($existing) {
            json_return_and_die(['error' => 'Already voted']);
        }

        $raw = $poll['obj'] ?? '';
        $obj = is_array($raw) ? $raw : json_decode($raw, true);
        if (!$obj) {
            json_return_and_die(['error' => 'Invalid poll data']);
        }

        $multiple   = !empty($obj['anyOf']);
        $optionsKey = $multiple ? 'anyOf' : 'oneOf';
        $validNames = array_map(
            fn($o) => htmlspecialchars_decode($o['name'] ?? '', ENT_QUOTES | ENT_HTML5),
            $obj[$optionsKey] ?? []
        );

        $responses = is_array($answer) ? $answer : [$answer];
        foreach ($responses as $res) {
            if (!in_array($res, $validNames, true)) {
                json_return_and_die(['error' => 'Invalid answer: ' . $res]);
            }
        }

        if (!$multiple) {
            $responses = [$responses[0]];
        }

        foreach ($responses as $res) {
            $uuid      = item_message_id();
            $answerMid = z_root() . '/item/' . $uuid;
            $now       = datetime_convert();

            $datarray = [
                'aid'             => $channel['channel_account_id'],
                'uid'             => intval($poll['uid']),
                'uuid'            => $uuid,
                'mid'             => $answerMid,
                'parent_mid'      => $poll['mid'],
                'thr_parent'      => $poll['mid'],
                'owner_xchan'     => $poll['author_xchan'],
                'author_xchan'    => $ob_hash,
                'created'         => $now,
                'edited'          => $now,
                'commented'       => $now,
                'received'        => $now,
                'changed'         => $now,
                'verb'            => 'Create',
                'obj_type'        => 'Answer',
                'title'           => $res,
                'body'            => '',
                'mimetype'        => 'text/bbcode',
                'allow_cid'       => '<' . $poll['author_xchan'] . '>',
                'allow_gid'       => '',
                'deny_cid'        => '',
                'deny_gid'        => '',
                'item_private'    => 1,
                'item_unseen'     => 0,
                'item_wall'       => 0,
                'item_origin'     => 1,
                'item_thread_top' => 0,
                'plink'           => $answerMid,
            ];

            $post = item_store($datarray);
            if ($post['success']) {
                retain_item($iid);
                Master::Summon(['Notifier', 'like', $post['item_id']]);
            }
        }

        json_return_and_die(['success' => true]);
    }

    // POST /api/item/:mid/reshare
    // Body: { body? }  (optional additional text above the share block)
    private function createReshare(string $mid): void
    {
        $uid = Auth::requireLocalJson();
        $ob_hash = get_observer_hash();

        $extraContent = trim(Auth::$parsedBody['body'] ?? '');

        $item = $this->resolveItem($mid, $ob_hash);
        if (!$item) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $iid = intval($item['id']);

        // Same split as expandShareTags: app items (articles, cards) must not go
        // through core Share::bbcode(), which would link the block at the
        // item's plink instead of its app page.
        $shareBlock = self::isAppItem($item)
            ? ''
            : (new \Zotlabs\Lib\Share($iid))->bbcode();

        // Core Share::bbcode() also refuses to wrap posts whose body already
        // contains [/share] (i.e. reshares). Build the block ourselves then.
        if (!$shareBlock) {
            $shareBlock = $this->buildShareBlock($item);
        }

        if (!$shareBlock) {
            json_return_and_die(['error' => 'Cannot reshare this post']);
        }

        $content = $extraContent
            ? $extraContent . "\r\n\r\n" . $shareBlock
            : $shareBlock;

        $acl = self::scopeToAcl('public', $uid);

        require_once('include/text.php');
        $extraTerms = $extraContent ? self::buildEmojiTerms($uid, $extraContent) : [];

        $datarray = self::buildItemArray(
            profileUid: $uid,
            content: $content,
            title: '',
            mimetype: 'text/bbcode',
            acl: $acl,
            isWall: true,
            term: $extraTerms,
        );

        $post = item_store($datarray);

        if (!$post['success']) {
            json_return_and_die(['error' => 'Failed to create reshare post']);
        }

        \Zotlabs\Daemon\Master::Summon(['Notifier', 'wall-new', $post['item_id']]);

        json_return_and_die([
            'success' => true,
            'iid'  => $post['item_id'],
            'mid'  => $datarray['mid'],
            'uuid' => $datarray['uuid'],
        ]);
    }

    // GET /api/item/:mid/compose
    // Returns the item's source fields for the edit composer. Full
    // [share …]…[/share] blocks are collapsed to compact [share=<id>][/share]
    // tags (the form the composer works with, mirroring core jot) so the
    // WYSIWYG never has to round-trip the attribute block.
    private function getComposeSource(string $mid): void
    {
        Auth::requireLocalGet();
        $ob_hash = get_observer_hash();

        $item = $this->resolveItem($mid, $ob_hash);
        if (!$item) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $body = $item['body'];
        if ($item['mimetype'] === 'text/bbcode') {
            $body = $this->collapseShareTags($body, $ob_hash);
        }

        // Categories come off the term table, not the item row — the composer
        // needs them to round-trip, otherwise saving would wipe them.
        $cats = q("SELECT term FROM term WHERE oid = %d AND otype = %d AND ttype = %d ORDER BY term ASC",
            intval($item['id']), intval(TERM_OBJ_POST), intval(TERM_CATEGORY));

        json_return_and_die([
            'success'  => true,
            'body'     => $body,
            'title'    => $item['title'],
            'summary'  => $item['summary'],
            'mimetype' => $item['mimetype'],
            'category' => $cats ? implode(',', array_column($cats, 'term')) : '',
        ]);
    }

    // GET /api/item/:id/sharepreview   (:id = numeric item id)
    // Returns the expanded [share …] block for a compact [share=<id>] tag so
    // the composer can render the reshared content inside the WYSIWYG.
    // Display-only: unlike the save-time expandShareTags (which must refuse
    // private items so they are never embedded into an outgoing body), the
    // preview may render anything the viewer can already see — including
    // their own or ACL-shared private posts.
    private function getSharePreview(string $id): void
    {
        Auth::requireLocalGet();

        $bb = '';
        $r = q("SELECT * FROM item WHERE id = %d LIMIT 1", intval($id));
        if ($r) {
            $sql_extra = item_permissions_sql(intval($r[0]['uid']));
            $v = q("SELECT * FROM item WHERE id = %d $sql_extra", intval($id));
            if ($v) {
                $bb = $this->buildShareBlock($v[0], forDisplay: true);
            }
        }

        if (!$bb) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        json_return_and_die(['success' => true, 'bbcode' => $bb]);
    }

    // GET /api/item/:id/cardpreview   (:id = numeric item id)
    // Returns the expanded [card …][/card] block for a compact [card=<id>] tag
    // so the composer can render the embedded card inside the WYSIWYG.
    // Display-only, same as getSharePreview above: the preview may render any
    // card the viewer can already see, since nothing is stored.
    private function getCardPreview(string $id): void
    {
        Auth::requireLocalGet();

        $bb = '';
        $r = q("SELECT * FROM item WHERE id = %d AND item_type = %d LIMIT 1",
            intval($id), intval(ITEM_TYPE_CARD));
        if ($r) {
            $sql_extra = item_permissions_sql(intval($r[0]['uid']));
            $v = q("SELECT * FROM item WHERE id = %d $sql_extra", intval($id));
            if ($v) {
                $bb = $this->buildCardBlock($v[0], forDisplay: true);
            }
        }

        if (!$bb) {
            json_return_and_die(['error' => 'Card not found or permission denied']);
        }

        json_return_and_die(['success' => true, 'bbcode' => $bb]);
    }

    // Expand compact [share=<item id>][/share] tags into the canonical
    // [share author=…]…[/share] block before storing — same mechanism as core
    // Item::post. Lib\Share enforces visibility (item_permissions_sql, no
    // private items), so a client-supplied id cannot leak restricted content.
    // Any content inside the compact tag is discarded, as core does.
    private function expandShareTags(string $body): string
    {
        if (!preg_match_all('/(\[share=(\d+)\](.*?)\[\/share\])/ism', $body, $match)) {
            return $body;
        }

        foreach ($match[2] as $i => $id) {
            $r = q("SELECT * FROM item WHERE id = %d LIMIT 1", intval($id));

            // Core Share::bbcode() hardcodes the item's plink as the block's
            // link, which would render an article or card embed as a generic
            // post pointing at /item/<uuid>. App items skip it and build the
            // block below, where appItemLink() supplies the /articles/ or
            // /cards/ URL both bbcode renderers key off.
            $bb = ($r && self::isAppItem($r[0]))
                ? ''
                : (new \Zotlabs\Lib\Share(intval($id)))->bbcode();

            if (!$bb) {
                // App items, and posts Share::bbcode() refuses because their
                // body already contains [/share] (nested reshares). Rebuild
                // the block ourselves with the same visibility rules
                // Lib\Share applies.
                if ($r && !intval($r[0]['item_private'])) {
                    $sql_extra = item_permissions_sql($r[0]['uid']);
                    $v = q("SELECT * FROM item WHERE id = %d $sql_extra", intval($id));
                    if ($v) {
                        $bb = $this->buildShareBlock($v[0]);
                    }
                }
            }

            if (!$bb) {
                // Silently dropping the tag would eat the reshared content on
                // save; refuse instead so the composer keeps the user's draft.
                Response::error(422, 'Shared post not found or cannot be reshared');
            }

            $body = str_replace($match[1][$i], $bb, $body);
        }

        return $body;
    }

    // Inverse of expandShareTags for the edit composer: replace each stored
    // top-level [share …message_id='…'…]…[/share] block with
    // [share=<id>][/share], resolving the shared item through the
    // permission-aware resolveItem. Blocks are located with a depth-aware
    // scan — nested reshares contain inner [/share] closers, and a non-greedy
    // regex would split the block and leave a stray outer [/share] behind.
    // Blocks whose target cannot be resolved are left untouched.
    private function collapseShareTags(string $body, string $ob_hash): string
    {
        $result = '';
        $cursor = 0;

        while (preg_match('/\[share\s/i', $body, $m, PREG_OFFSET_CAPTURE, $cursor)) {
            $start = $m[0][1];
            $end = self::findShareEnd($body, $start);
            if ($end < 0) {
                break; // unbalanced — leave the rest untouched
            }

            $result .= substr($body, $cursor, $start - $cursor);
            $block = substr($body, $start, $end - $start);

            // message_id must come from the outer block's attributes (before
            // the first ']'), never from a nested block's attributes.
            // Only collapse when the matching save-time expander could
            // re-expand the tag — otherwise the stored block would be lost on
            // the next save. Unresolvable blocks stay verbatim; the editor
            // renders them from their own attributes.
            //
            // A card embed is stored as a share block too (see
            // Concerns\EmbedsCards), so this one scan serves both: an
            // ITEM_TYPE_CARD target collapses to [card=<id>] for
            // expandCardTags, anything else to [share=<id>] for
            // expandShareTags.
            $collapsed = $block;
            if (preg_match("/^\[share\s[^\]]*message_id='([^']+)'/is", $block, $mm)) {
                $target = $this->resolveItem($mm[1], $ob_hash);
                if ($target && $target['mimetype'] === 'text/bbcode') {
                    $isCard = intval($target['item_type']) === ITEM_TYPE_CARD;
                    // Cards additionally allow the owner's own private ones —
                    // buildCardBlock()'s gate, mirrored here so the two
                    // directions agree about what is embeddable.
                    $embeddable = !intval($target['item_private'])
                        || ($isCard && intval($target['uid']) === intval(local_channel()));
                    if ($embeddable) {
                        $collapsed = $isCard
                            ? '[card=' . intval($target['id']) . '][/card]'
                            : '[share=' . intval($target['id']) . '][/share]';
                    }
                }
            }

            $result .= $collapsed;
            $cursor = $end;
        }

        return $result . substr($body, $cursor);
    }

    // End offset (exclusive) of the balanced [share]…[/share] block that
    // opens at $start, counting nested [share openings; -1 if unbalanced.
    private static function findShareEnd(string $body, int $start): int
    {
        $depth = 0;
        $pos = $start;

        while (preg_match('/\[share[=\s]|\[\/share\]/i', $body, $t, PREG_OFFSET_CAPTURE, $pos)) {
            $tok = $t[0][0];
            $tokPos = $t[0][1];
            $pos = $tokPos + strlen($tok);

            if (strcasecmp($tok, '[/share]') === 0) {
                $depth--;
                if ($depth <= 0) {
                    return $pos;
                }
            } else {
                $depth++;
            }
        }

        return -1;
    }

    // $forDisplay: composer previews may include private items the viewer
    // can already see; save-time expansion must never embed them.
    private function buildShareBlock(array $item, bool $forDisplay = false): string
    {
        if (($item['item_private'] && !$forDisplay) || $item['mimetype'] !== 'text/bbcode') {
            return '';
        }

        $rows = [$item];
        xchan_query($rows, true);
        $author  = $rows[0]['author'] ?? [];
        $network = $author['xchan_network'] ?? '';
        // quote='true' tells Activity::encode_item to strip the block and
        // federate it as quoteUrl = the block's link attribute (Lib/Activity.php
        // ~677). That only works when the link is an AS-resolvable object, i.e.
        // an ordinary post's plink. An app item's link is its HTML app page, so
        // quoting it federates as an unfetchable "RE: <url>" and the remote
        // renders bare text — send the block inline instead, which is also what
        // buildCardBlock has always done.
        $quote = (!self::isAppItem($item) && in_array($network, ['zot6', 'activitypub']))
            ? "quote='true'"
            : '';

        $bb  = "[share author='" . urlencode($author['xchan_name'] ?? '') . "'\n";
        $bb .= "\tprofile='" . ($author['xchan_url'] ?? '') . "'\n";
        $bb .= "\tavatar='" . ($author['xchan_photo_s'] ?? '') . "'\n";
        // App items (articles, cards) link to their own page, not their plink:
        // that is what makes both bbcode renderers label the block correctly.
        $bb .= "\tlink='" . (self::appItemLink($item) ?: ($item['plink'] ?? '')) . "'\n";
        $bb .= "\tauth='" . ($network === 'zot6' ? 'true' : 'false') . "'\n";
        $bb .= "\tposted='" . ($item['created'] ?? '') . "'\n";
        $bb .= "\tmessage_id='" . ($item['mid'] ?? '') . "'\n";
        if ($quote) {
            $bb .= "\t$quote\n";
        }
        $bb .= ']';

        if ($item['title']) {
            $bb .= '[h3][b]' . $item['title'] . '[/b][/h3]' . "\r\n";
        }

        $bb .= $item['body'];
        $bb .= '[/share]';

        return $bb;
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    private function requireLocalChannel(): void
    {
        if (!local_channel()) {
            json_return_and_die(['error' => 'Authentication required']);
        }
    }

    private function requireCsrf(): void
    {
        Csrf::validate();
    }

    // Nginx normalises "https://" to "https:/" in URL paths before passing the
    // request to PHP via $_GET['q']. After explode/implode the reconstructed mid
    // ends up with only one slash after the protocol colon. Restore the missing slash.
    private static function fixProtocolSlashes(string $mid): string
    {
        if (str_starts_with($mid, 'https:/') && !str_starts_with($mid, 'https://')) {
            return 'https://' . substr($mid, 7);
        }
        if (str_starts_with($mid, 'http:/') && !str_starts_with($mid, 'http://')) {
            return 'http://' . substr($mid, 6);
        }
        return $mid;
    }
}
