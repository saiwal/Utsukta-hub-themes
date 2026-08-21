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

        // URL: /api/stream-widgets/tags
        //      /api/stream-widgets/categories
        //      /api/stream-widgets/popular
        //      /api/stream-widgets/series
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
            default        => Response::error(404, "Unknown sub-resource: {$sub}"),
        };
    }

    // ── /api/stream-widgets/tags ─────────────────────────────────────────────

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
               AND item.item_wall       = 1
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

    // ── /api/stream-widgets/categories ──────────────────────────────────────

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
               AND item.item_wall       = 1
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

    // ── /api/stream-widgets/popular ──────────────────────────────────────────
    // Most commented thread-top items for the channel, permission-aware.

    private function getPopular(): void
    {
        require_once 'include/conversation.php';

        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $limit         = min(20, max(1, (int) ($_GET['limit'] ?? 5)));
        $observer_hash = get_observer_hash();

        $item_type_val = $type === 'articles' ? ITEM_TYPE_ARTICLE : ITEM_TYPE_POST;
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
               AND item.item_wall       = 1
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
            'title'        => $item['title'] ?? '',
            'body'         => $item['body']  ?? '',
            'authorName'   => Response::decodeEntities($item['author']['xchan_name'] ?? ''),
            'authorAvatar' => $item['author']['xchan_photo_m'] ?? '',
            'created'      => $item['created'],
            'commentCount' => (int) $item['comment_count'],
        ], $items);

        Response::send(['popular' => $popular]);
    }

    // ── /api/stream-widgets/series ───────────────────────────────────────────
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

    // ── /api/stream-widgets/archive ──────────────────────────────────────────

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
               AND item.item_wall       = 1
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

    // ── /api/stream-widgets/archive-days ─────────────────────────────────────
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
               AND item.item_wall       = 1
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
