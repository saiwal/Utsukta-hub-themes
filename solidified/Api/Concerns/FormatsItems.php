<?php
// Api/Concerns/FormatsItems.php
namespace Theme\Solidified\Api\Concerns;

trait FormatsItems
{
    private function formatItem(array $item, string $observer_xchan): array
    {
        $liked = $disliked = $repeated = false;

        if ($observer_xchan && !empty($item['reaction_verbs'])) {
            foreach (explode('|', $item['reaction_verbs']) as $rv) {
                if (!str_contains($rv, ':'))
                    continue;
                [$verb, $xchan] = explode(':', $rv, 2);
                if ($xchan !== $observer_xchan)
                    continue;
                if ($verb === 'Like')
                    $liked = true;
                if ($verb === 'Dislike')
                    $disliked = true;
                if ($verb === 'Announce')
                    $repeated = true;
            }
        }
        $is_boost = ($item['verb'] === 'Announce');
        $boosted_by = null;

        if ($is_boost) {
            // For boosts: author = repeater, original post is in obj JSON
            $obj = $item['obj'] ?? '';
            if (is_string($obj) && $obj) {
                $obj = json_decode($obj, true) ?? [];
            }
            if (!is_array($obj)) {
                $obj = [];
            }

            // obj['attributedTo'] or obj['actor'] holds the original author URL
            $orig_url = '';
            if (!empty($obj['attributedTo'])) {
                $orig_url = is_array($obj['attributedTo'])
                    ? ($obj['attributedTo']['id'] ?? $obj['attributedTo']['url'] ?? '')
                    : $obj['attributedTo'];
            } elseif (!empty($obj['actor'])) {
                $orig_url = is_array($obj['actor'])
                    ? ($obj['actor']['id'] ?? '')
                    : $obj['actor'];
            }

            // Look up original author xchan by URL
            $orig_xchan = [];
            if ($orig_url) {
                $r = q("SELECT xchan_name, xchan_addr, xchan_url, xchan_photo_m, xchan_photo_mimetype
                FROM xchan WHERE xchan_url = '%s' OR xchan_id_url = '%s' LIMIT 1",
                    dbesc($orig_url), dbesc($orig_url));
                if ($r) {
                    $orig_xchan = $r[0];
                }
            }

            // boosted_by = the repeater (current author)
            $boosted_by = [
                'name' => $item['author']['xchan_name'] ?? '',
                'url' => $item['author']['xchan_url'] ?? '',
                'photo' => $item['author']['xchan_photo_m'] ?? '',
            ];

            // If we found the original author, override author for display
            if ($orig_xchan) {
                $item['_orig_xchan'] = $orig_xchan;
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
                intval($item['item_notshown']) ? 'notshown' : null,
                intval($item['item_unseen']) ? 'unseen' : null,
            ])),
            'author' => [
                'name' => ($is_boost && !empty($item['_orig_xchan'])) ? $item['_orig_xchan']['xchan_name'] : ($item['author']['xchan_name'] ?? ''),
                'address' => ($is_boost && !empty($item['_orig_xchan'])) ? $item['_orig_xchan']['xchan_addr'] : ($item['author']['xchan_addr'] ?? ''),
                'url' => ($is_boost && !empty($item['_orig_xchan'])) ? $item['_orig_xchan']['xchan_url'] : ($item['author']['xchan_url'] ?? ''),
                'photo' => [
                    'src' => ($is_boost && !empty($item['_orig_xchan'])) ? $item['_orig_xchan']['xchan_photo_m'] : ($item['author']['xchan_photo_m'] ?? ''),
                    'mimetype' => ($is_boost && !empty($item['_orig_xchan'])) ? $item['_orig_xchan']['xchan_photo_mimetype'] : ($item['author']['xchan_photo_mimetype'] ?? ''),
                ],
            ],
            'boosted_by' => $boosted_by,
            'permalink' => $item['plink'] ?? '',
            'viewer_liked' => $liked,
            'viewer_disliked' => $disliked,
            'viewer_repeated' => $repeated,
        ];
    }
}
