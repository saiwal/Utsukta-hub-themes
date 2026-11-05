<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Cache;

/**
 * Backlinks and the content graph of one channel's own writing.
 *
 *   GET /spa/links/graph?nick=          → { nodes, edges }   (404 when the owner has Graph off)
 *   GET /spa/links/backlinks?path=      → { node, from[] }  (path = an SPA url; empty when off)
 *   GET /spa/links/resolve?nick=&title= → { url|null }      ([[Title]] outside wikis)
 *
 * Nodes are the owner's articles, cards, notes and wiki pages (always), plus
 * own top-level posts that link or are linked. Edges come from [[wikilinks]]
 * and internal permalinks (/display, /item, /articles, /cards, /wiki) in the
 * body. An unresolved [[Title]] becomes a `missing` node, as in Obsidian.
 *
 * The whole graph is built in one pass and cached per channel; every endpoint
 * slices it, so the widget, the map and [[link]] resolution can't disagree.
 * Visitors get it filtered behind item_permissions_sql + per-app perms — an
 * edge survives only when both ends are visible to them.
 */
class Links
{
    private const CACHE_AGE = '15 MINUTE';

    /** Router drops this on every SPA write by the channel, so own edits show at once. */
    public static function cacheKey(int $uid): string
    {
        return 'spa:links:' . $uid;
    }

    /**
     * The channel's frontend-feature overrides (pconfig spa/disabled_frontend_modules,
     * written by the SPA's Settings → Integrations): ids whose state differs
     * from the module's default. Graph defaults to off, so listed = on.
     */
    public static function frontendOverrides(int $uid): array
    {
        $raw = get_pconfig($uid, 'spa', 'disabled_frontend_modules', '');
        $ids = is_array($raw) ? $raw : json_decode((string) $raw, true);
        return is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];
    }

    /** Has the channel owner switched the Graph feature on? */
    public static function enabledFor(int $uid): bool
    {
        return in_array('graph', self::frontendOverrides($uid), true);
    }

    /** The wiki addon's classes load only when one of its hooks runs; pull them in. */
    public static function wikiLoaded(): bool
    {
        if (!class_exists('\\NativeWiki', false) && is_file('addon/wiki/Lib/NativeWiki.php')) {
            require_once 'addon/wiki/Lib/NativeWiki.php';
        }
        return class_exists('\\NativeWiki', false);
    }

    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/security.php';
        require_once 'include/permissions.php';

        switch (\App::$argv[2] ?? '') {
            case 'graph':
                $channel = $this->channelByNick((string) ($_GET['nick'] ?? ''));
                if (!self::enabledFor(intval($channel['channel_id']))) {
                    Response::error(404, 'Graph is not enabled for this channel');
                }
                $g = $this->visibleGraph($channel);
                Response::send(['nodes' => $g['nodes'], 'edges' => $g['edges']]);

            case 'backlinks':
                $this->backlinks((string) ($_GET['path'] ?? ''));

            case 'resolve':
                $channel = $this->channelByNick((string) ($_GET['nick'] ?? ''));
                $g  = $this->visibleGraph($channel);
                $id = $g['index']['t:' . mb_strtolower(trim((string) ($_GET['title'] ?? '')))] ?? null;
                $node = $id !== null ? $this->nodeById($g, $id) : null;
                Response::send(['url' => $node['url'] ?? null]);
        }
        Response::error(404, 'Unknown links endpoint');
    }

    private function channelByNick(string $nick): array
    {
        $c = $nick !== '' ? channelx_by_nick($nick) : null;
        if (!$c || $c['channel_removed']) {
            Response::error(404, 'Channel not found');
        }
        return $c;
    }

    private function backlinks(string $path): never
    {
        $ref = self::refFromUrl($path, null);
        if (!$ref) {
            Response::send(['node' => null, 'from' => []]);
        }
        if ($ref['nick'] !== null) {
            $channel = $this->channelByNick($ref['nick']);
        } else {
            // /display/<uuid> carries no nick — the wall copy names the owner.
            $r = q("SELECT uid FROM item WHERE uuid = '%s' AND item_wall = 1 LIMIT 1", dbesc($ref['uuid']));
            $channel = $r ? channelx_by_n(intval($r[0]['uid'])) : null;
            if (!$channel) {
                Response::send(['node' => null, 'from' => []]);
            }
        }

        // Backlinks are part of the Graph feature; [[Title]] resolution isn't.
        if (!self::enabledFor(intval($channel['channel_id']))) {
            Response::send(['node' => null, 'from' => []]);
        }

        $g  = $this->visibleGraph($channel);
        $id = $g['index'][$ref['key']] ?? null;
        $node = $id !== null ? $this->nodeById($g, $id) : null;
        if (!$node) {
            Response::send(['node' => null, 'from' => []]);
        }

        $from = [];
        foreach ($g['edges'] as $e) {
            if ($e['target'] === $id && ($n = $this->nodeById($g, $e['source']))) {
                $from[$n['id']] = $n;
            }
        }
        Response::send(['node' => $node, 'from' => array_values($from)]);
    }

    private function nodeById(array $g, string $id): ?array
    {
        foreach ($g['nodes'] as $n) {
            if ($n['id'] === $id) return $n;
        }
        return null;
    }

    // ── Visibility ───────────────────────────────────────────────────────────

    private function visibleGraph(array $channel): array
    {
        $uid = intval($channel['channel_id']);
        $g   = $this->graph($channel);

        if (local_channel() !== $uid) {
            $ob    = get_observer_hash();
            $perms = get_all_perms($uid, $ob);
            $typeOk = [
                'post'    => !empty($perms['view_stream']),
                'article' => !empty($perms['view_pages']),
                'card'    => !empty($perms['view_pages']),
                'wiki'    => !empty($perms['view_wiki']),
                'note'    => false, // personal notes are never anyone else's business
            ];
            $iids = [];
            foreach ($g['nodes'] as $n) {
                if (!empty($typeOk[$n['type']])) $iids[] = intval($n['iid']);
            }
            $ok = [];
            if ($iids) {
                // Wiki pages are stored unpublished (and hidden when their wiki
                // is), so those two flags only gate ordinary items.
                $rows = dbq("SELECT id FROM item
                    WHERE uid = $uid AND id IN (" . implode(',', $iids) . ")
                      AND item_deleted = 0 AND item_blocked = 0 AND item_delayed = 0
                      AND item_pending_remove = 0
                      AND ((item_hidden = 0 AND item_unpublished = 0) OR resource_type = 'nwikipage')
                      " . item_permissions_sql($uid, $ob)) ?: [];
                $ok = array_flip(array_map(fn($r) => intval($r['id']), $rows));
            }
            $keep = [];
            foreach ($g['nodes'] as $n) {
                if ($n['type'] !== 'missing' && isset($ok[intval($n['iid'])])) $keep[$n['id']] = true;
            }
            $edges = [];
            foreach ($g['edges'] as $e) {
                if (!isset($keep[$e['source']])) continue;
                // A missing target is only a title written in a visible source.
                if (str_starts_with($e['target'], 'missing:')) $keep[$e['target']] = true;
                if (isset($keep[$e['target']])) $edges[] = $e;
            }
            $g['nodes'] = array_values(array_filter($g['nodes'], fn($n) => isset($keep[$n['id']])));
            $g['edges'] = $edges;
            $g['index'] = array_filter($g['index'], fn($id) => isset($keep[$id]));
        }

        $g['nodes'] = array_map(function ($n) { unset($n['iid']); return $n; }, $g['nodes']);
        return $g;
    }

    // ── Build (cached, observer-independent) ────────────────────────────────

    private function graph(array $channel): array
    {
        $key = self::cacheKey(intval($channel['channel_id']));
        $hit = Cache::get($key, self::CACHE_AGE);
        if ($hit && is_array($g = json_decode($hit, true))) {
            return $g;
        }
        $g = $this->build($channel);
        Cache::set($key, json_encode($g));
        return $g;
    }

    private function build(array $channel): array
    {
        $uid  = intval($channel['channel_id']);
        $hash = dbesc($channel['channel_hash']);
        $nick = $channel['channel_address'];

        $nodes = [];   // id => node (+ transient 'body', 'wiki')
        $index = [];   // ref key => node id

        $add = function (array $row, string $type, string $url) use (&$nodes, &$index) {
            $id = $row['uuid'];
            $nodes[$id] = [
                'id'    => $id,
                'iid'   => intval($row['id']),
                'type'  => $type,
                'title' => self::title($row),
                'url'   => $url,
                'body'  => (string) $row['body'],
                'wiki'  => $row['wiki'] ?? null,
            ];
            $index['u:' . $id] = $id;
            if (trim((string) $row['title']) !== '') {
                $index['t:' . mb_strtolower(trim($row['title']))] ??= $id;
            }
        };

        // Wiki pages first, so [[Title]] prefers a wiki page over an article of
        // the same name. Each save is a new row; the latest per page wins.
        if (self::wikiLoaded()) {
            $wikis = [];
            foreach (dbq("SELECT item.resource_id, iconfig.v FROM item
                    INNER JOIN iconfig ON iconfig.iid = item.id AND iconfig.cat = 'wiki' AND iconfig.k = 'rawName'
                    WHERE item.uid = $uid AND item.resource_type = 'nwiki' AND item.item_deleted = 0") ?: [] as $w) {
                $wikis[$w['resource_id']] = \NativeWiki::name_encode($w['v']);
            }
            $pages = dbq("SELECT id, uuid, title, body, resource_id FROM item
                WHERE id IN (SELECT MAX(id) FROM item
                    WHERE uid = $uid AND resource_type = 'nwikipage' AND item_deleted = 0
                    GROUP BY resource_id, title)") ?: [];
            foreach ($pages as $p) {
                $wenc = $wikis[$p['resource_id']] ?? null;
                if ($wenc === null) continue;
                $penc = \NativeWiki::name_encode($p['title']);
                $p['wiki'] = $wenc;
                $add($p, 'wiki', "/wiki/$nick/$wenc/$penc");
                $index["w:$wenc/$penc"] = $p['uuid'];
            }
        }

        $types = [ITEM_TYPE_ARTICLE => 'article', ITEM_TYPE_CARD => 'card', ITEM_TYPE_CUSTOM => 'note'];
        $rows = dbq("SELECT id, uuid, title, body, item_type FROM item
            WHERE uid = $uid AND item_type IN (" . implode(',', array_keys($types)) . ")
              AND item_thread_top = 1 AND item_deleted = 0 AND author_xchan = '$hash'") ?: [];
        $slugs = [];
        if ($rows) {
            $ns = [item_type_to_namespace(ITEM_TYPE_ARTICLE), item_type_to_namespace(ITEM_TYPE_CARD)];
            foreach (dbq("SELECT iid, v FROM iconfig WHERE cat = 'system'
                    AND k IN ('" . implode("','", $ns) . "')
                    AND iid IN (" . implode(',', array_map(fn($r) => intval($r['id']), $rows)) . ")") ?: [] as $s) {
                $slugs[intval($s['iid'])] = $s['v'];
            }
        }
        foreach ($rows as $r) {
            $type  = $types[intval($r['item_type'])];
            $slug  = $slugs[intval($r['id'])] ?? null;
            $ident = $slug ?? $r['uuid'];
            $url   = match ($type) {
                'article' => "/articles/$nick/" . rawurlencode($ident),
                'card'    => "/cards/$nick/" . rawurlencode($ident),
                'note'    => '/notepad?note=' . $r['uuid'],
            };
            $add($r, $type, $url);
            if ($type !== 'note') {
                $p = $type[0];
                $index["$p:{$r['uuid']}"] = $r['uuid'];
                if ($slug !== null) $index["$p:$slug"] = $r['uuid'];
            }
        }

        // Own top-level posts: only those whose body could hold a link are read
        // as sources; posts that are only link *targets* are fetched below.
        $post = function (array $p) use ($add) { $add($p, 'post', '/display/' . $p['uuid']); };
        $postSql = "uid = $uid AND item_type = " . ITEM_TYPE_POST . " AND item_thread_top = 1
            AND item_wall = 1 AND item_deleted = 0 AND author_xchan = '$hash'";
        foreach (dbq("SELECT id, uuid, title, body FROM item WHERE $postSql AND (body LIKE '%[[%'
                OR body LIKE '%/display/%' OR body LIKE '%/item/%' OR body LIKE '%/articles/%'
                OR body LIKE '%/cards/%' OR body LIKE '%/wiki/%')") ?: [] as $p) {
            $post($p);
        }

        // Extract refs.
        $refs = [];
        foreach ($nodes as $id => $n) {
            $refs[$id] = self::extractRefs($n['body'], $nick, $n['wiki']);
        }

        $want = [];
        foreach ($refs as $rs) {
            foreach ($rs as $r) {
                if (is_string($r) && str_starts_with($r, 'u:') && !isset($index[$r])) $want[] = "'" . dbesc(substr($r, 2)) . "'";
            }
        }
        if ($want) {
            foreach (dbq("SELECT id, uuid, title, body FROM item WHERE $postSql
                    AND uuid IN (" . implode(',', array_unique($want)) . ")") ?: [] as $p) {
                $post($p);
            }
        }

        $edges = [];
        $linked = [];
        foreach ($refs as $src => $rs) {
            foreach ($rs as $r) {
                // [[Title]] inside a wiki: same wiki first, then any title.
                $tgt = null;
                if (is_array($r)) {
                    [$wikiKey, $titleKey] = $r;
                    $tgt = ($wikiKey ? ($index[$wikiKey] ?? null) : null) ?? $index[$titleKey] ?? null;
                    if ($tgt === null) {
                        $tgt = 'missing:' . substr($titleKey, 2);
                        $nodes[$tgt] ??= ['id' => $tgt, 'iid' => 0, 'type' => 'missing',
                            'title' => $r[2], 'url' => null, 'body' => '', 'wiki' => null];
                    }
                } else {
                    $tgt = $index[$r] ?? null;
                }
                if ($tgt === null || $tgt === $src) continue;
                $edges["$src $tgt"] = ['source' => $src, 'target' => $tgt];
                $linked[$src] = $linked[$tgt] = true;
            }
        }

        // Posts are only interesting when connected; everything else always shows.
        $out = [];
        foreach ($nodes as $id => $n) {
            if ($n['type'] === 'post' && !isset($linked[$id])) continue;
            unset($n['body'], $n['wiki']);
            $out[] = $n;
        }
        $keep = array_flip(array_column($out, 'id'));

        return [
            'nodes' => $out,
            'edges' => array_values($edges),
            'index' => array_filter($index, fn($id) => isset($keep[$id])),
        ];
    }

    // ── Parsing (pure; see Links.test.php) ───────────────────────────────────

    /**
     * Link refs in a body: index keys for permalinks, and
     * [wikiKey|null, titleKey, rawTitle] triples for [[wikilinks]].
     */
    public static function extractRefs(string $body, string $nick, ?string $wikiEnc): array
    {
        $refs = [];
        if (str_contains($body, '[[') && preg_match_all('/\[\[([^\[\]|]+)(?:\|[^\[\]]*)?\]\]/u', $body, $m)) {
            foreach ($m[1] as $t) {
                $t = trim(html_entity_decode($t, ENT_QUOTES, 'UTF-8'));
                // [[i]] / [[/i]] list-marker debris turns up in real bbcode
                // bodies; a page title is never one character or a closing tag.
                if (mb_strlen($t) < 2 || $t[0] === '/') continue;
                $wikiKey = ($wikiEnc !== null && self::wikiLoaded())
                    ? "w:$wikiEnc/" . \NativeWiki::name_encode($t) : null;
                $refs[] = [$wikiKey, 't:' . mb_strtolower($t), $t];
            }
        }
        $root = preg_quote(z_root(), '#');
        // Absolute links to this hub, or relative ones right after =, (, a quote or space.
        if (preg_match_all('#(?:' . $root . '|(?<=[=("\'\s]))(/(?:display|item|articles|cards|wiki)/[^\s\[\]<>"\']+)#', $body, $m)) {
            foreach ($m[1] as $url) {
                // Wiki names encode as Page(20)Two, so parens are legal — only
                // an unbalanced closer (markdown "(link)") and prose punctuation go.
                $url = rtrim($url, '.,;:!?');
                while (str_ends_with($url, ')') && substr_count($url, ')') > substr_count($url, '(')) {
                    $url = rtrim(substr($url, 0, -1), '.,;:!?');
                }
                if ($r = self::refFromUrl($url, $nick)) $refs[] = $r['key'];
            }
        }
        return $refs;
    }

    /**
     * An SPA/permalink path → ['key', 'nick'|null, 'uuid'?]. With $onlyNick
     * set, links into another channel's content are ignored.
     */
    public static function refFromUrl(string $url, ?string $onlyNick): ?array
    {
        $path = parse_url(html_entity_decode($url, ENT_QUOTES, 'UTF-8'), PHP_URL_PATH) ?: '';
        $seg  = array_values(array_filter(explode('/', $path), 'strlen'));
        $kind = $seg[0] ?? '';

        if (($kind === 'display' || $kind === 'item') && count($seg) === 2) {
            $uuid = rawurldecode($seg[1]);
            return ['key' => "u:$uuid", 'nick' => null, 'uuid' => $uuid];
        }
        $nick = isset($seg[1]) ? rawurldecode($seg[1]) : null;
        if ($nick === null || ($onlyNick !== null && $nick !== $onlyNick)) {
            return null;
        }
        if (($kind === 'articles' || $kind === 'cards') && count($seg) === 3
            && !in_array($seg[2], ['series', 'deck'], true)) {
            return ['key' => $kind[0] . ':' . rawurldecode($seg[2]), 'nick' => $nick];
        }
        if ($kind === 'wiki' && count($seg) === 4) {
            return ['key' => "w:{$seg[2]}/{$seg[3]}", 'nick' => $nick];
        }
        return null;
    }

    /** Title, or for untitled posts/notes a short plain-text snippet. */
    public static function title(array $row): string
    {
        $t = trim(html_entity_decode((string) $row['title'], ENT_QUOTES, 'UTF-8'));
        if ($t !== '') return $t;
        // ponytail: crude bbcode/markdown strip — good enough for an 60-char label.
        $s = preg_replace(['/\[[^\]]*\]/', '/[#*_>`]+/', '/\s+/'], ['', '', ' '], (string) $row['body']);
        $s = trim(html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8'));
        return $s === '' ? '…' : (mb_strlen($s) > 60 ? mb_substr($s, 0, 60) . '…' : $s);
    }
}
