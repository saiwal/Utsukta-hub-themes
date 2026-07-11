<?php
// Api/Concerns/FormatsItems.php
namespace Theme\Solidified\Api\Concerns;

trait FormatsItems
{
    // Find deleted items that are parents of the given comments but absent from
    // the result set. Returns pre-formatted stubs (same shape as formatItem output)
    // so the frontend can build a complete thread tree without gaps.
    protected function deletedParentStubs(array $comments, string $rootMid): array
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
            'message_top'      => $d['parent_mid'],
            'created'          => $d['created'],
            'edited'           => $d['created'],
            'commented'        => $d['created'],
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
            'author'           => ['name' => '', 'address' => '', 'url' => '', 'network' => '', 'photo' => ['src' => '', 'mimetype' => '']],
            'owner'            => null,
            'permalink'        => '',
            'location'         => '',
            'coord'            => '',
            'expires'          => null,
            'viewer_liked'     => false,
            'viewer_disliked'  => false,
            'viewer_repeated'  => false,
            'viewer_attending' => false,
            'viewer_declining' => false,
            'viewer_maybe'     => false,
            'viewer_following' => false,
            'attach'           => [],
        ], $deleted ?: []);
    }

    // Stamp viewer_following on each item. Follow/Ignore activities are stored
    // local-only in the *viewer's* channel (core Mod_Subthread semantics), so
    // they must be matched by parent_mid within the viewer's uid — the parent
    // item *id* differs between the viewer's copy and the copy being displayed
    // (channel pages, pubstream, display). Latest activity wins.
    protected function applyViewerFollowing(array &$items, string $observer_xchan): void
    {
        $uid = intval(local_channel());
        if (!$uid || !$observer_xchan || empty($items)) {
            return;
        }

        $mids = array_unique(array_filter(array_column($items, 'parent_mid')));
        if (empty($mids)) {
            return;
        }

        $inList = implode("','", array_map('dbesc', $mids));
        $obs    = dbesc($observer_xchan);
        $frows  = dbq(
            "SELECT parent_mid, verb FROM item
             WHERE uid = $uid
               AND parent_mid IN ('$inList')
               AND author_xchan = '$obs'
               AND verb IN ('Follow', 'Ignore')
               AND item_deleted = 0
             ORDER BY created DESC, id DESC"
        );

        $map = [];
        foreach (($frows ?: []) as $fr) {
            if (!isset($map[$fr['parent_mid']])) {
                $map[$fr['parent_mid']] = ($fr['verb'] === 'Follow');
            }
        }

        foreach ($items as &$item) {
            $item['viewer_following'] = $map[$item['parent_mid']] ?? false;
        }
        unset($item);
    }

    private static function normalizeAttach(array $items): array
    {
        $root = z_root();
        return array_map(function (array $a) use ($root): array {
            if (isset($a['href']) && str_starts_with($a['href'], '/')) {
                $a['href'] = $root . $a['href'];
            }
            return $a;
        }, $items);
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

    private function formatItem(array $item, string $observer_xchan): array
    {
        $liked = $disliked = $repeated = $attending = $declining = $maybe = false;

        if ($observer_xchan && !empty($item['reaction_verbs'])) {
            foreach (explode('|', $item['reaction_verbs']) as $rv) {
                if (!str_contains($rv, ':'))
                    continue;
                [$verb, $xchan] = explode(':', $rv, 2);
                if ($xchan !== $observer_xchan)
                    continue;
                if ($verb === 'Like')       $liked      = true;
                if ($verb === 'Dislike')    $disliked   = true;
                if ($verb === 'Announce')   $repeated   = true;
                if ($verb === 'Accept')     $attending  = true;
                if ($verb === 'Reject')     $declining  = true;
                if ($verb === 'TentativeAccept') $maybe = true;
            }
        }

        return [
            'uuid' => $item['uuid'],
            'mid' => $item['mid'],
            'parent_mid' => $item['parent_mid'],
            'thr_parent' => $item['thr_parent'],
            'message_top' => intval($item['item_thread_top'])
                ? $item['mid']
                : $item['thr_parent'],
            'created' => $item['created'],
            'edited' => $item['edited'],
            'commented' => $item['commented'],
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
                intval($item['item_starred']) ? 'starred' : null,
                intval($item['item_notshown'])
                    ? ($observer_xchan && $observer_xchan === ($item['author_xchan'] ?? '') ? 'expired' : 'notshown')
                    : null,
                intval($item['item_unseen']) ? 'unseen' : null,
            ])),
            'author' => [
                'name'    => urldecode($item['author']['xchan_name']         ?? ''),
                'address' => $item['author']['xchan_addr']         ?? '',
                'url'     => $item['author']['xchan_url']          ?? '',
                'hash'    => $item['author']['xchan_hash']         ?? '',
                'network' => $item['author']['xchan_network']      ?? '',
                'photo'   => [
                    'src'      => $item['author']['xchan_photo_m']        ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'owner' => (function () use ($item): ?array {
                // Hubzilla Announce: original content is fetched, booster stored in source_xchan
                if (!empty($item['source_xchan']) && !empty($item['source'])) {
                    $x = $item['source'];
                    return [
                        'name'    => urldecode($x['xchan_name']            ?? ''),
                        'address' => $x['xchan_addr']            ?? '',
                        'url'     => $x['xchan_url']             ?? '',
                        'hash'    => $x['xchan_hash']            ?? '',
                        'photo'   => [
                            'src'      => $x['xchan_photo_m']        ?? '',
                            'mimetype' => $x['xchan_photo_mimetype'] ?? '',
                        ],
                    ];
                }
                // ActivityPub (Lemmy communities, Mastodon boosts etc.): the delivering
                // connection is owner_xchan when it differs from the content's author_xchan
                if ($item['owner_xchan'] !== $item['author_xchan'] && !empty($item['owner'])) {
                    $x = $item['owner'];
                    return [
                        'name'    => urldecode($x['xchan_name']            ?? ''),
                        'address' => $x['xchan_addr']            ?? '',
                        'url'     => $x['xchan_url']             ?? '',
                        'hash'    => $x['xchan_hash']            ?? '',
                        'photo'   => [
                            'src'      => $x['xchan_photo_m']        ?? '',
                            'mimetype' => $x['xchan_photo_mimetype'] ?? '',
                        ],
                    ];
                }
                return null;
            })(),
            'permalink' => $item['plink'] ?? '',
            'location' => $item['location'] ?? '',
            'coord' => $item['coord'] ?? '',
            'expires' => (isset($item['expires']) && $item['expires'] > NULL_DATE)
                ? $item['expires']
                : null,
            'viewer_liked' => $liked,
            'viewer_disliked' => $disliked,
            'viewer_repeated' => $repeated,
            'viewer_attending' => $attending,
            'viewer_declining' => $declining,
            'viewer_maybe' => $maybe,
            'viewer_following' => (bool)($item['viewer_following'] ?? false),
            'attach' => self::normalizeAttach($item['attach'] ? json_decode($item['attach'], true) : []),
            'poll'   => self::extractPoll($item, $observer_xchan),
        ];
    }
}
