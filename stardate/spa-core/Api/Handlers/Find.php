<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;

/**
 * Unified search for the command palette: your own item table, files and
 * bookmarks — or, with `channel=<nick>`, what the observer (local, remote or
 * anonymous) may see of that channel: wall posts, articles, cards, webpages,
 * wiki pages and files, each behind its view_* permission and the row ACLs.
 * Notes and bookmarks are never anyone else's.
 *
 *   GET /spa/find?term=&types=post,article,card,webpage,wiki,note,file,bookmark
 *                &tag=&dbegin=YYYY-MM-DD&dend=YYYY-MM-DD&limit=&channel=
 *   → { results: [{ type, id, title, snippet, url, created, external? }] }
 *
 * (`term`, not `q`: Hubzilla routes on $_GET['q'].) Up to `limit` hits per
 * type, newest first. A tag filter matches hashtags and categories and so
 * skips files and bookmarks; a date filter skips bookmarks (they have none).
 */
class Find
{
    private const TYPES = ['post', 'article', 'card', 'webpage', 'wiki', 'note', 'file', 'bookmark'];

    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/taxonomy.php';
        require_once 'include/security.php';
        require_once 'include/permissions.php';

        $nick = trim((string) ($_GET['channel'] ?? ''));
        if ($nick === '') {
            Auth::requireLocalGet();
            $channel = \App::get_channel();
        } else {
            $channel = channelx_by_nick($nick);
            if (!$channel || $channel['channel_removed']) {
                Response::error(404, 'Channel not found');
            }
        }
        $uid     = intval($channel['channel_id']);
        $nick    = $channel['channel_address'];
        $owner   = local_channel() === $uid;
        $ob      = get_observer_hash();
        $term    = trim((string) ($_GET['term'] ?? ''));
        $tag     = trim((string) ($_GET['tag'] ?? ''));
        $limit   = min(20, max(1, intval($_GET['limit'] ?? 6)));
        $types   = array_values(array_intersect(self::TYPES, explode(',', (string) ($_GET['types'] ?? '')))) ?: self::TYPES;
        $dbegin  = $this->date('dbegin');
        $dend    = $this->date('dend');

        if (!$owner) {
            if (observer_prohibited()) {
                Response::error(403, 'Permission denied');
            }
            $perms = get_all_perms($uid, $ob);
            $need  = ['post' => 'view_stream', 'article' => 'view_pages', 'card' => 'view_pages',
                'webpage' => 'view_pages', 'wiki' => 'view_wiki', 'file' => 'view_storage'];
            $types = array_values(array_filter($types, fn($t) => isset($need[$t]) && !empty($perms[$need[$t]])));
        }

        if (mb_strlen($term) < 2 && $tag === '') {
            Response::send(['results' => []]);
        }

        $like = $term === '' ? null
            : dbesc('%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($term)) . '%');

        $itemSql = '';
        if ($like !== null) $itemSql .= " AND (LOWER(item.title) LIKE '$like' OR LOWER(item.body) LIKE '$like')";
        if ($tag !== '')    $itemSql .= term_query('item', $tag, TERM_HASHTAG, TERM_CATEGORY);
        if ($dbegin)        $itemSql .= " AND item.created >= '" . dbesc($dbegin) . "'";
        if ($dend)          $itemSql .= " AND item.created <= '" . dbesc($dend) . "'";
        if (!$owner)        $itemSql .= item_permissions_sql($uid, $ob);
        // A channel's item rows include everything ever delivered to it; a
        // visitor searches only what it published on its own wall.
        $wall = $owner ? '' : ' AND item.item_wall = 1';

        $out = [];
        foreach ($types as $type) {
            $rows = match ($type) {
                'post'    => $this->items($uid, ITEM_TYPE_POST, $itemSql . $wall, $limit, false),
                'article' => $this->items($uid, ITEM_TYPE_ARTICLE, $itemSql, $limit, true),
                'card'    => $this->items($uid, ITEM_TYPE_CARD, $itemSql, $limit, true),
                'webpage' => $this->items($uid, ITEM_TYPE_WEBPAGE, $itemSql, $limit, true),
                'note'    => $this->items($uid, ITEM_TYPE_CUSTOM, $itemSql, $limit, true),
                'wiki'    => $this->wikiPages($uid, $itemSql, $limit),
                'file'    => $tag === '' && $like !== null ? $this->files($uid, $like, $dbegin, $dend, $limit, $owner ? null : $ob) : [],
                'bookmark'=> $tag === '' && $like !== null && !$dbegin && !$dend ? $this->bookmarks($uid, $like, $limit) : [],
            };
            foreach ($rows as $r) {
                $out[] = $this->format($type, $r, $nick, $term);
            }
        }

        Response::send(['results' => $out]);
    }

    private function date(string $k): string
    {
        $v = (string) ($_GET[$k] ?? '');
        return ($v !== '' && is_a_date_arg($v)) ? datetime_convert(date_default_timezone_get(), '', notags($v)) : '';
    }

    private function items(int $uid, int $type, string $sql, int $limit, bool $topOnly): array
    {
        $top = $topOnly ? ' AND item.item_thread_top = 1' : '';
        // Not `Add`: every article/card create or edit also stores an empty
        // collection-sync activity flagged thread-top (conversation target).
        // A webpage opens only by its page link (iconfig system/WEBPAGE).
        return dbq("SELECT item.id, item.uuid, item.title, item.body, item.created, slug.v AS slug FROM item
            LEFT JOIN iconfig slug ON slug.iid = item.id AND slug.cat = 'system' AND slug.k = 'WEBPAGE'
            WHERE item.uid = $uid $top AND item.verb != 'Add' " . item_normal($uid, 'item', $type) . " $sql
            ORDER BY item.created DESC LIMIT $limit") ?: [];
    }

    private function wikiPages(int $uid, string $sql, int $limit): array
    {
        if (!Links::wikiLoaded()) return [];
        $rows = dbq("SELECT item.id, item.uuid, item.title, item.body, item.created, iconfig.v AS wiki
            FROM item
            INNER JOIN item w ON w.uid = item.uid AND w.resource_type = 'nwiki'
                AND w.resource_id = item.resource_id AND w.item_deleted = 0
            INNER JOIN iconfig ON iconfig.iid = w.id AND iconfig.cat = 'wiki' AND iconfig.k = 'rawName'
            WHERE item.id IN (SELECT MAX(id) FROM item
                WHERE uid = $uid AND resource_type = 'nwikipage' AND item_deleted = 0
                GROUP BY resource_id, title) $sql
            ORDER BY item.created DESC LIMIT $limit") ?: [];
        return $rows;
    }

    /** $ob null = the owner; otherwise the observer the ACLs are checked for. */
    private function files(int $uid, string $like, string $dbegin, string $dend, int $limit, ?string $ob): array
    {
        $sql = '';
        if ($dbegin) $sql .= " AND created >= '" . dbesc($dbegin) . "'";
        if ($dend)   $sql .= " AND created <= '" . dbesc($dend) . "'";
        if ($ob === null) {
            return dbq("SELECT id, hash, filename, display_path, is_dir, created FROM attach
                WHERE uid = $uid AND LOWER(filename) LIKE '$like' $sql
                ORDER BY created DESC LIMIT $limit") ?: [];
        }

        // As in Files::search(): the ACL is per row, so a hit only counts when
        // every ancestor folder is visible too — its path is in the result.
        // ponytail: filters the newest 200 matches, so a visitor can get fewer
        // than $limit when most hits are hidden; page the query if that bites.
        $acl  = permissions_sql($uid, $ob, 'attach');
        $hits = dbq("SELECT id, hash, filename, display_path, is_dir, created, folder FROM attach
            WHERE uid = $uid AND LOWER(filename) LIKE '$like' $sql $acl
            ORDER BY created DESC LIMIT 200") ?: [];
        if (!$hits) return [];
        $dirs = array_column(dbq("SELECT hash, folder FROM attach WHERE uid = $uid AND is_dir = 1 $acl") ?: [], 'folder', 'hash');
        return array_slice(array_filter($hits, fn($r) => self::visiblePath((string) $r['folder'], $dirs)), 0, $limit);
    }

    /** Does the folder chain from $parent up to the root stay inside $dirs (hash => parent)? */
    public static function visiblePath(string $parent, array $dirs): bool
    {
        // Bounded by count($dirs) so a corrupt folder cycle can't spin.
        for ($i = 0; $parent !== '' && $i <= count($dirs); $i++) {
            if (!isset($dirs[$parent])) return false;
            $parent = (string) $dirs[$parent];
        }
        return $parent === '';
    }

    private function bookmarks(int $uid, string $like, int $limit): array
    {
        return dbq("SELECT mi.mitem_id, mi.mitem_link, mi.mitem_desc FROM menu_item mi
            INNER JOIN menu m ON m.menu_id = mi.mitem_menu_id
            WHERE mi.mitem_channel_id = $uid AND (m.menu_flags & " . intval(MENU_BOOKMARK) . ")
              AND (LOWER(mi.mitem_desc) LIKE '$like' OR LOWER(mi.mitem_link) LIKE '$like')
            ORDER BY mi.mitem_desc ASC LIMIT $limit") ?: [];
    }

    private function format(string $type, array $r, string $nick, string $term): array
    {
        $dec = fn($s) => html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($type === 'file') {
            $path = (string) $r['display_path'];
            $dir  = intval($r['is_dir']) ? $path : (str_contains($path, '/') ? dirname($path) : '');
            return ['type' => 'file', 'id' => $r['hash'], 'title' => $r['filename'], 'snippet' => $path,
                'url' => "/cloud/$nick" . ($dir !== '' ? '/' . implode('/', array_map('rawurlencode', explode('/', $dir))) : ''),
                'created' => $r['created']];
        }
        if ($type === 'bookmark') {
            $link = $dec($r['mitem_link']);
            // Links into this hub become SPA paths, so the router opens them in place.
            $local = str_starts_with($link, z_root() . '/');
            return ['type' => 'bookmark', 'id' => (string) $r['mitem_id'], 'title' => $dec($r['mitem_desc']) ?: $link,
                'snippet' => $link, 'url' => $local ? substr($link, strlen(z_root())) : $link,
                'created' => null, 'external' => !$local];
        }

        $url = match ($type) {
            'post'    => '/display/' . $r['uuid'],
            'article' => "/articles/$nick/" . $r['uuid'],
            'card'    => "/cards/$nick/" . $r['uuid'],
            'webpage' => "/page/$nick/" . implode('/', array_map('rawurlencode',
                explode('/', urldecode(str_replace('%2f', '/', (string) $r['slug']))))),
            'note'    => '/notepad?note=' . $r['uuid'],
            'wiki'    => "/wiki/$nick/" . \NativeWiki::name_encode($r['wiki']) . '/' . \NativeWiki::name_encode($r['title']),
        };
        return ['type' => $type, 'id' => $r['uuid'], 'title' => Links::title($r),
            'snippet' => self::excerpt((string) $r['body'], $term), 'url' => $url, 'created' => $r['created']];
    }

    /** ~140 chars of plain text around the first hit (or the start). */
    public static function excerpt(string $body, string $term): string
    {
        $s = preg_replace(['/\[[^\]]*\]/', '/[#*_>`]+/', '/\s+/'], ['', '', ' '], $body);
        $s = trim(html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8'));
        $at = $term !== '' ? mb_stripos($s, $term) : false;
        $start = $at === false ? 0 : max(0, $at - 50);
        $cut = mb_substr($s, $start, 140);
        return ($start > 0 ? '…' : '') . $cut . (mb_strlen($s) > $start + 140 ? '…' : '');
    }
}
