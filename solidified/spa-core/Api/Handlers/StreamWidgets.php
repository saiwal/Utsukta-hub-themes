<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Concerns\ReactionCounts;
use Utsukta\SpaCore\Api\Response;

class StreamWidgets
{
    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/channel.php';
        require_once 'include/taxonomy.php';

        // URL: /spa/stream-widgets/tags
        //      /spa/stream-widgets/categories
        //      /spa/stream-widgets/popular
        //      /spa/stream-widgets/series
        $sub = \App::$argv[2] ?? null;

        if (!$sub) {
            Response::error(400, 'No sub-resource specified');
        }

        match ($sub) {
            'tags'         => $this->getTags(),
            'categories'   => $this->getCategories(),
            'popular'      => $this->getPopular(),
            'archive'      => $this->getArchive(),
            'archive-days' => $this->getArchiveDays(),
            'series'       => $this->getSeries(),
            'related'      => $this->getRelated(),
            default        => Response::error(404, "Unknown sub-resource: {$sub}"),
        };
    }

    // ── /spa/stream-widgets/tags ─────────────────────────────────────────────

    private function getTags(): void
    {
        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $item_type_val = $this->itemTypeValue($type);
        $item_normal   = item_normal($uid, 'item', $item_type_val);
        $perm_sql      = item_permissions_sql($uid);

        $rows = dbq(
            "SELECT term.term, COUNT(term.term) AS total
             FROM term
             LEFT JOIN item ON term.oid = item.id
             WHERE term.uid   = " . intval($uid) . "
               AND term.ttype = " . intval(TERM_HASHTAG) . "
               AND term.otype = " . intval(TERM_OBJ_POST) . "
               AND item.item_thread_top = 1
               " . $this->wallSql() . "
               $perm_sql $item_normal
             GROUP BY term.term
             ORDER BY total DESC
             LIMIT 100"
        );

        $tags = array_map(fn($r) => [
            'name'  => $r['term'],
            'count' => (int) $r['total'],
        ], $rows ?: []);

        Response::send(['tags' => $tags]);
    }

    // ── /spa/stream-widgets/categories ──────────────────────────────────────

    private function getCategories(): void
    {
        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $item_type_val = $this->itemTypeValue($type);
        $item_normal   = item_normal($uid, 'item', $item_type_val);
        $perm_sql      = item_permissions_sql($uid);

        $rows = dbq(
            "SELECT term.term, COUNT(term.term) AS total
             FROM term
             LEFT JOIN item ON term.oid = item.id
             WHERE term.uid   = " . intval($uid) . "
               AND term.ttype = " . intval(TERM_CATEGORY) . "
               AND term.otype = " . intval(TERM_OBJ_POST) . "
               AND item.item_thread_top = 1
               " . $this->wallSql() . "
               $perm_sql $item_normal
             GROUP BY term.term
             ORDER BY total DESC"
        );

        $categories = array_map(fn($r) => [
            'name'  => $r['term'],
            'slug'  => $r['term'],
            'count' => (int) $r['total'],
        ], $rows ?: []);

        Response::send(['categories' => $categories]);
    }

    // ── /spa/stream-widgets/popular ──────────────────────────────────────────
    // Most commented thread-top items for the channel, permission-aware.

    private function getPopular(): void
    {
        require_once 'include/conversation.php';

        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $limit         = min(20, max(1, (int) ($_GET['limit'] ?? 5)));
        $observer_hash = get_observer_hash();

        $item_type_val = $this->itemTypeValue($type);
        $item_normal   = item_normal($uid, 'item', $item_type_val);

        $permission_sql = item_permissions_sql($uid, $observer_hash);

        $items = dbq(
            "SELECT item.uuid,
                    item.title,
                    item.body,
                    item.created,
                    item.author_xchan,
                    " . ReactionCounts::commentCountSubquery() . " AS comment_count
             FROM item
             WHERE item.uid             = " . intval($uid) . "
               AND item.item_thread_top = 1
               " . $this->wallSql() . "
               AND item.item_type       = " . intval($item_type_val) . "
               AND item.item_private   IN (0, 1)
               AND item.obj_type       NOT IN ('Event', '" . dbesc(ACTIVITY_OBJ_EVENT) . "')
               $item_normal
               $permission_sql
             ORDER BY comment_count DESC, item.created DESC
             LIMIT " . intval($limit)
        );

        if (!$items) {
            Response::send(['popular' => []]);
            return;
        }

        xchan_query($items, true);

        $popular = array_map(fn($item) => [
            'uuid'         => $item['uuid'],
            'title'        => Response::decodeEntities($item['title'] ?? ''),
            'body'         => $item['body']  ?? '',
            'authorName'   => Response::decodeEntities($item['author']['xchan_name'] ?? ''),
            'authorAvatar' => $item['author']['xchan_photo_m'] ?? '',
            'created'      => $item['created'],
            'commentCount' => (int) $item['comment_count'],
        ], $items);

        Response::send(['popular' => $popular]);
    }

    // ── /spa/stream-widgets/related ──────────────────────────────────────────
    // ?item=<uuid|slug>&type=<posts|articles|cards>[&channel_nick=]
    // Other thread-top items of the same channel and type sharing hashtags or
    // categories with the given one. Each shared term scores 1/ln(1+df) — df
    // being how many visible items carry it — so a rare tag outweighs a common
    // one; categories count double. Posts resolve by the owner's wall copy
    // (item_wall = 1), so /display/<uuid> of a remote post has no related list.

    private function getRelated(): void
    {
        require_once 'include/conversation.php';

        $type          = $this->itemType();
        $item_type_val = $this->itemTypeValue($type);
        $ident         = dbesc((string) ($_GET['item'] ?? ''));
        $limit         = min(20, max(1, (int) ($_GET['limit'] ?? 5)));

        if ($ident === '') {
            Response::error(400, 'No item specified');
        }

        if ($type === 'posts') {
            $src = dbq("SELECT id, uid FROM item
                WHERE uuid = '$ident' AND item_wall = 1 AND item_thread_top = 1
                  AND item_type = " . ITEM_TYPE_POST . " AND item_deleted = 0 LIMIT 1");
        } else {
            $uid = $this->resolveUid();
            $src = dbq("SELECT id, uid FROM item
                WHERE uid = $uid AND uuid = '$ident'
                  AND item_type = $item_type_val AND item_deleted = 0 LIMIT 1")
                ?: dbq("SELECT item.id, item.uid FROM item
                INNER JOIN iconfig ON iconfig.iid = item.id
                WHERE item.uid = $uid AND iconfig.cat = 'system'
                  AND iconfig.k = '" . item_type_to_namespace($item_type_val) . "'
                  AND iconfig.v = '$ident'
                  AND item.item_type = $item_type_val AND item.item_deleted = 0 LIMIT 1");
        }

        if (!$src) {
            Response::send(['related' => []]);
        }

        $src_id   = (int) $src[0]['id'];
        $uid      = (int) $src[0]['uid'];
        $perm_sql = item_permissions_sql($uid, get_observer_hash());
        $normal   = item_normal($uid, 'item', $item_type_val);
        $types    = intval(TERM_HASHTAG) . ',' . intval(TERM_CATEGORY);

        // The source item's own terms — behind the same permission check, so
        // a private post's tags can't be probed through what it relates to.
        $terms = dbq("SELECT DISTINCT term.term FROM term
            INNER JOIN item ON item.id = term.oid
            WHERE term.oid = $src_id AND term.otype = " . intval(TERM_OBJ_POST) . "
              AND term.ttype IN ($types) $perm_sql");

        if (!$terms) {
            Response::send(['related' => []]);
        }

        $in = implode(',', array_map(fn($t) => "'" . dbesc($t['term']) . "'", $terms));

        // ponytail: one row per (candidate, shared term), scored in PHP; capped
        // at 2000 rows — a SQL-side GROUP BY if channels outgrow that.
        $rows = dbq("SELECT term.oid, term.ttype, term.term FROM term
            INNER JOIN item ON item.id = term.oid
            WHERE term.uid = $uid AND term.otype = " . intval(TERM_OBJ_POST) . "
              AND term.ttype IN ($types) AND term.term IN ($in)
              AND item.item_thread_top = 1 AND item.item_type = $item_type_val
              " . $this->wallSql() . "
              $normal $perm_sql
            LIMIT 2000");

        $df = [];
        foreach ($rows ?: [] as $r) {
            $df[$r['term']][$r['oid']] = true;
        }

        $scores = [];
        foreach ($rows ?: [] as $r) {
            if ((int) $r['oid'] === $src_id) continue;
            $w = ((int) $r['ttype'] === TERM_CATEGORY ? 2 : 1) / log(1 + count($df[$r['term']]));
            $scores[$r['oid']] = ($scores[$r['oid']] ?? 0) + $w;
        }

        if (!$scores) {
            Response::send(['related' => []]);
        }

        $items = dbq("SELECT item.id, item.uuid, item.title, item.body, item.created,
                item.author_xchan, " . ReactionCounts::commentCountSubquery() . " AS comment_count
            FROM item WHERE item.id IN (" . implode(',', array_map('intval', array_keys($scores))) . ")");

        // Highest score first, newest on a tie.
        usort($items, fn($a, $b) => [$scores[$b['id']], $b['created']] <=> [$scores[$a['id']], $a['created']]);
        $items = array_slice($items, 0, $limit);
        xchan_query($items, true);

        Response::send(['related' => array_map(fn($item) => [
            'uuid'         => $item['uuid'],
            'title'        => Response::decodeEntities($item['title'] ?? ''),
            'body'         => $item['body'] ?? '',
            'authorName'   => Response::decodeEntities($item['author']['xchan_name'] ?? ''),
            'authorAvatar' => $item['author']['xchan_photo_m'] ?? '',
            'created'      => $item['created'],
            'commentCount' => (int) $item['comment_count'],
        ], $items)]);
    }

    // ── /spa/stream-widgets/series ───────────────────────────────────────────
    // Distinct ordered groupings for the channel, with member counts. Only
    // meaningful for the two types that have them: type=articles (series,
    // iconfig cat 'article'/'series') and type=cards (decks, cat 'card'/'deck').
    // Any other type has no grouping concept and returns an empty list.

    private function getSeries(): void
    {
        $uid            = $this->resolveUid();
        $permission_sql = item_permissions_sql($uid);
        $type           = $this->itemType();

        [$cfg_cat, $cfg_key] = match ($type) {
            'articles' => ['article', 'series'],
            'cards'    => ['card', 'deck'],
            default    => ['', ''],
        };

        if (!$cfg_cat) {
            Response::send(['series' => []]);
        }

        $rows = dbq(
            "SELECT s.v AS name, COUNT(*) AS total
             FROM iconfig s
             INNER JOIN item ON item.id = s.iid
             WHERE s.cat = '$cfg_cat' AND s.k = '$cfg_key'
               AND item.uid          = " . intval($uid) . "
               AND item.item_type    = " . $this->itemTypeValue($type) . "
               AND item.item_deleted = 0
               $permission_sql
             GROUP BY s.v
             ORDER BY s.v ASC"
        );

        $series = array_map(fn($r) => [
            'name'  => $r['name'],
            'count' => (int) $r['total'],
        ], $rows ?: []);

        Response::send(['series' => $series]);
    }

    // ── /spa/stream-widgets/archive ──────────────────────────────────────────

    private function getArchive(): void
    {
        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $item_type_val = $this->itemTypeValue($type);
        $item_normal   = item_normal($uid, 'item', $item_type_val);
        $perm_sql      = item_permissions_sql($uid);

        // item.created is stored in UTC, but the widget's dbegin/dend click
        // filters are computed in the site's local timezone (see Channel.php
        // et al., which convert them via datetime_convert(site_tz, 'UTC', ...)).
        // Bucketing by raw UTC would misfile any post made near local
        // midnight into the wrong month: a dot shows, but the click finds
        // nothing. Shift by the site's current UTC offset before grouping —
        // not per-row DST-aware, but matches this app's existing
        // datetime_convert usage far better than no conversion at all.
        $offset = (new \DateTimeZone(date_default_timezone_get()))
            ->getOffset(new \DateTime('now', new \DateTimeZone('UTC')));
        $localCreated = "DATE_ADD(item.created, INTERVAL $offset SECOND)";

        $rows = dbq(
            "SELECT YEAR($localCreated) AS yr, MONTH($localCreated) AS mo, COUNT(*) AS total
             FROM item
             WHERE item.uid             = " . intval($uid) . "
               AND item.item_thread_top = 1
               " . $this->wallSql() . "
               AND item.item_deleted    = 0
               AND item.item_private   IN (0, 1)
               AND item.verb           != 'Add'
               $perm_sql $item_normal
             GROUP BY yr, mo
             ORDER BY yr DESC, mo DESC"
        );

        $years = [];
        foreach ($rows ?: [] as $row) {
            $yr = (int) $row['yr'];
            $mo = (int) $row['mo'];
            $n  = (int) $row['total'];
            if (!isset($years[$yr])) {
                $years[$yr] = ['year' => $yr, 'count' => 0, 'months' => []];
            }
            $years[$yr]['count']    += $n;
            $years[$yr]['months'][]  = ['month' => $mo, 'count' => $n];
        }

        Response::send(['archive' => array_values($years)]);
    }

    // ── /spa/stream-widgets/archive-days ─────────────────────────────────────
    // Day-of-month post counts for a single year+month, used to plot dots on
    // a calendar-style archive widget.

    private function getArchiveDays(): void
    {
        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $item_type_val = $this->itemTypeValue($type);
        $item_normal   = item_normal($uid, 'item', $item_type_val);
        $perm_sql      = item_permissions_sql($uid);

        $year  = (int) ($_GET['year']  ?? 0);
        $month = (int) ($_GET['month'] ?? 0);

        if ($year < 1 || $month < 1 || $month > 12) {
            Response::error(400, 'Valid year and month are required');
        }

        // item.created is stored in UTC, but the calendar grid (and the
        // dbegin/dend the day-click handler sends) buckets by the site's
        // local timezone — convert the local month boundaries to UTC for the
        // query, then bucket each row back into local time. Grouping
        // directly on DAY(item.created) would misfile posts made near local
        // midnight into the wrong UTC day: a dot shows, but clicking it
        // finds nothing.
        $tz         = date_default_timezone_get();
        $monthStart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        [$nextYear, $nextMonth] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];
        $monthEnd   = sprintf('%04d-%02d-01 00:00:00', $nextYear, $nextMonth);

        $utcFrom = datetime_convert($tz, 'UTC', $monthStart);
        $utcTo   = datetime_convert($tz, 'UTC', $monthEnd);

        $rows = dbq(
            "SELECT item.created
             FROM item
             WHERE item.uid             = " . intval($uid) . "
               AND item.item_thread_top = 1
               " . $this->wallSql() . "
               AND item.item_deleted    = 0
               AND item.item_private   IN (0, 1)
               AND item.verb           != 'Add'
               AND item.created        >= '" . dbesc($utcFrom) . "'
               AND item.created        <  '" . dbesc($utcTo)   . "'
               $perm_sql $item_normal"
        );

        $counts = [];
        foreach ($rows ?: [] as $row) {
            $day = (int) datetime_convert('UTC', $tz, $row['created'], 'j');
            $counts[$day] = ($counts[$day] ?? 0) + 1;
        }
        ksort($counts);

        $days = [];
        foreach ($counts as $day => $count) {
            $days[] = ['day' => $day, 'count' => $count];
        }

        Response::send(['days' => $days]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve channel uid from ?channel_nick=, falling back to local_channel().
     */
    private function resolveUid(): int
    {
        $nick = $_GET['channel_nick'] ?? null;

        if ($nick) {
            $channel = channelx_by_nick($nick);
            if (!$channel) {
                Response::error(404, 'Channel not found');
            }
            return (int) $channel['channel_id'];
        }

        $uid = local_channel();
        if (!$uid) {
            Response::error(401, 'Authentication required');
        }

        return (int) $uid;
    }

    /**
     * Read ?type= param. Returns 'articles', 'cards', 'notes', or 'posts' (default).
     */
    private function itemType(): string
    {
        $type = $_GET['type'] ?? '';
        return in_array($type, ['articles', 'cards', 'notes'], true) ? $type : 'posts';
    }

    /**
     * item_wall only separates a channel's own posts from delivered ones.
     * Article/card/note rows are always the owner's, but older or imported
     * ones carry item_wall = 0 — core's card_tagadelic() doesn't filter on it
     * and neither does the app's own list, so the widgets mustn't either.
     */
    private function wallSql(): string
    {
        return $this->itemType() === 'posts' ? ' AND item.item_wall = 1 ' : '';
    }

    /**
     * Map an itemType() result to its item.item_type column value.
     */
    private function itemTypeValue(string $type): int
    {
        return match ($type) {
            'articles' => ITEM_TYPE_ARTICLE,
            'cards'    => ITEM_TYPE_CARD,
            'notes'    => ITEM_TYPE_CUSTOM,
            default    => ITEM_TYPE_POST,
        };
    }

    /**
     * Lowercase slug: spaces → hyphens, strip non-alphanumeric.
     */
    private function slugify(string $term): string
    {
        $slug = mb_strtolower($term);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        return trim($slug, '-');
    }
}
