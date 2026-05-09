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
        if (!$boosted_by && intval($item['item_thread_top']) === 1) {
            $repeaters = $repeaters_map[$item['mid']] ?? [];
            if ($repeaters) {
                // Show the first repeater; frontend can show "+N more" if count > 1
                $boosted_by = $repeaters[0];
                $boosted_by['others'] = array_slice($repeaters, 1);
            }
        }
        // Detect a repeated post by presence of [share] tag in body
        $boosted_by = null;
        $share_match = [];
        /* logger('FORMATITEM body: ' . substr($item['body'] ?? '', 0, 150), LOGGER_DEBUG); */
        if (preg_match('/\[share\s+([^\]]+)\]/s', $item['body'], $share_match)) {
            logger('SHARE MATCHED', LOGGER_DEBUG);
            // This is a repeat — author of this item is the repeater
            // Parse original author from [share] attributes
            $attrs = $share_match[1];
            $orig_name = '';
            $orig_profile = '';
            $orig_avatar = '';

            if (preg_match("/author='([^']+)'/", $attrs, $m))
                $orig_name = html_entity_decode($m[1]);
            if (preg_match("/profile='([^']+)'/", $attrs, $m))
                $orig_profile = $m[1];
            if (preg_match("/avatar='([^']+)'/", $attrs, $m))
                $orig_avatar = $m[1];

            // boosted_by = the repeater (current item author)
            $boosted_by = [
                'name' => $item['author']['xchan_name'] ?? '',
                'url' => $item['author']['xchan_url'] ?? '',
                'photo' => $item['author']['xchan_photo_m'] ?? '',
            ];
            logger('BOOSTED_BY: ' . json_encode($boosted_by), LOGGER_DEBUG);
            // Override displayed author to be the original post author
            $item['_share_author'] = [
                'name' => $orig_name,
                'url' => $orig_profile,
                'photo' => $orig_avatar,
            ];
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
                'name' => isset($item['_share_author']) ? $item['_share_author']['name'] : ($item['author']['xchan_name'] ?? ''),
                'address' => $item['author']['xchan_addr'] ?? '',
                'url' => isset($item['_share_author']) ? $item['_share_author']['url'] : ($item['author']['xchan_url'] ?? ''),
                'photo' => [
                    'src' => isset($item['_share_author']) ? $item['_share_author']['photo'] : ($item['author']['xchan_photo_m'] ?? ''),
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'boosted_by' => $boosted_by,
            'repeated_by' => $repeaters_map[$item['mid']] ?? [],  // repeat: array of repeaters
            'permalink' => $item['plink'] ?? '',
            'viewer_liked' => $liked,
            'viewer_disliked' => $disliked,
            'viewer_repeated' => $repeated,
        ];
    }
}
