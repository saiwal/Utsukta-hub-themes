<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;

/**
 * GET /spa/emoji-groups → { slug: "Smileys & Emotion", … }
 *
 * /smilies (core get_emojis()) carries no categories. The emoji addon builds
 * its set from addon/emoji/emoji.json, whose entries do — keyed by the same
 * slug /smilies uses. Anything absent here (core's hubzilla pack,
 * images/emoji/custom/*) is a server emoji, which the picker groups by pack.
 */
class EmojiGroups {

    public function get(): void {
        $file = 'addon/emoji/emoji.json';
        $out = [];
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (is_array($data)) {
            foreach ($data as $info) {
                if (isset($info['slug'], $info['group']))
                    $out[$info['slug']] = $info['group'];
            }
        }
        header('Cache-Control: public, max-age=86400');
        Response::send((object) $out);
    }
}
