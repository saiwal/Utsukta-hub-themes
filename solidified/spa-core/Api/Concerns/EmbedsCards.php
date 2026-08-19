<?php
namespace Utsukta\SpaCore\Api\Concerns;

use Utsukta\SpaCore\Api\Response;

/**
 * The [card] embed: a compact [card=<item id>][/card] token the composer
 * inserts, expanded at save time into a self-contained attribute block that
 * renders without a further fetch, and collapsed back to the compact form
 * when an item is reopened for editing.
 *
 * Structurally this mirrors Item.php's [share] machinery (expandShareTags /
 * collapseShareTags / buildShareBlock), with two deliberate differences noted
 * at their call sites below: card blocks carry no body, and every attribute
 * value is urlencode()d.
 *
 * Shared by Item.php (posts and comments) and Handlers\Cards (a card body
 * embedding another card).
 */
trait EmbedsCards
{
    /**
     * Build the canonical self-contained [card …][/card] block for an item.
     *
     * $forDisplay mirrors buildShareBlock(): composer previews may render a
     * private card the viewer can already see, save-time expansion may not
     * embed one that isn't theirs.
     */
    protected function buildCardBlock(array $item, bool $forDisplay = false): string
    {
        if ($item['mimetype'] !== 'text/bbcode') {
            return '';
        }

        // ponytail: owner-only gate on private cards — looser than [share],
        // which refuses item_private outright. A private card embedded into a
        // more widely addressed post puts its title/teaser/cover in front of a
        // larger audience than the card's own ACL, and travels on with any
        // reshare. Upgrade path: compare the host post's ACL against the
        // card's here and refuse on widening.
        if (intval($item['item_private']) && !$forDisplay
            && intval($item['uid']) !== intval(local_channel())) {
            return '';
        }

        $nick = '';
        $c = q("SELECT channel_address FROM channel WHERE channel_id = %d LIMIT 1",
            intval($item['uid']));
        if ($c) {
            $nick = $c[0]['channel_address'];
        }
        if (!$nick) {
            return '';
        }

        [$slug, $deck, $template] = self::cardIconfig(intval($item['id']));

        $rows = [$item];
        xchan_query($rows, true);
        $author = $rows[0]['author'] ?? [];

        $link = z_root() . '/cards/' . $nick . '/' . ($slug ?: $item['uuid']);

        // Every value is urlencode()d — bbcode.ts's attr() helper
        // decodeURIComponent()s it back, and it keeps quotes and brackets in a
        // title from breaking the attribute string (which is also why
        // collapseCardTags below needs no depth-aware scan).
        $attrs = [
            'mid'         => $item['mid'] ?? '',
            'nick'        => $nick,
            'uuid'        => $item['uuid'] ?? '',
            'title'       => Response::decodeEntities($item['title'] ?? ''),
            'teaser'      => self::cardTeaser($item),
            'cover'       => self::cardCover($item),
            'deck'        => $deck ?? '',
            'template'    => $template ?: 'freeform',
            'author'      => $author['xchan_name'] ?? '',
            'authorurl'   => $author['xchan_url'] ?? '',
            'authorphoto' => $author['xchan_photo_s'] ?? '',
            'link'        => $link,
        ];

        $bb = '[card';
        foreach ($attrs as $k => $v) {
            $bb .= "\n\t$k='" . urlencode((string) $v) . "'";
        }
        $bb .= '][/card]';

        return $bb;
    }

    /**
     * Expand compact [card=<item id>][/card] tags into the canonical block
     * before storing — the same mechanism (and the same 422-on-failure
     * discipline) as Item.php's expandShareTags.
     */
    protected function expandCardTags(string $body): string
    {
        if (!preg_match_all('/(\[card=(\d+)\](.*?)\[\/card\])/ism', $body, $match)) {
            return $body;
        }

        foreach ($match[2] as $i => $id) {
            $bb = '';

            $r = q("SELECT * FROM item WHERE id = %d AND item_type = %d AND item_deleted = 0 LIMIT 1",
                intval($id), intval(ITEM_TYPE_CARD));

            if ($r) {
                // Re-query behind the owner's permission SQL so a
                // client-supplied id cannot surface a card the composing user
                // is not permitted to see.
                $sql_extra = item_permissions_sql(intval($r[0]['uid']));
                $v = q("SELECT * FROM item WHERE id = %d $sql_extra", intval($id));
                if ($v) {
                    $bb = $this->buildCardBlock($v[0]);
                }
            }

            if (!$bb) {
                // Silently dropping the tag would eat the embed on save;
                // refuse instead so the composer keeps the user's draft.
                Response::error(422, 'Card not found or cannot be embedded');
            }

            $body = str_replace($match[1][$i], $bb, $body);
        }

        return $body;
    }

    /**
     * Inverse of expandCardTags for the edit composer: replace each stored
     * [card …mid='…'…][/card] block with [card=<id>][/card].
     *
     * $resolve is a permission-aware mid -> ?array lookup (Item.php's
     * resolveItem), passed in so this trait doesn't have to own one.
     *
     * Unlike collapseShareTags this needs no depth-aware walk: a card block
     * carries no body, and buildCardBlock urlencode()s every attribute, so no
     * '[', ']' or nested '[card' can occur inside one.
     */
    protected function collapseCardTags(string $body, callable $resolve): string
    {
        return preg_replace_callback(
            '/\[card\s[^\]]*\]\s*\[\/card\]/is',
            function (array $m) use ($resolve): string {
                if (!preg_match("/\smid='([^']*)'/is", $m[0], $mm)) {
                    return $m[0];
                }
                $target = $resolve(urldecode($mm[1]));
                // Only collapse when expandCardTags could re-expand it —
                // otherwise the stored block would be lost on the next save.
                if (!$target
                    || intval($target['item_type']) !== ITEM_TYPE_CARD
                    || $target['mimetype'] !== 'text/bbcode') {
                    return $m[0];
                }
                return '[card=' . intval($target['id']) . '][/card]';
            },
            $body
        ) ?? $body;
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /** [slug, deck, template] for an item, read straight off iconfig. */
    private static function cardIconfig(int $iid): array
    {
        $slug = '';
        $deck = null;
        $template = '';

        $rows = q("SELECT cat, k, v FROM iconfig WHERE iid = %d", intval($iid)) ?: [];
        foreach ($rows as $cfg) {
            if ($cfg['cat'] === 'system' && $cfg['k'] === item_type_to_namespace(ITEM_TYPE_CARD)) {
                $slug = urldecode($cfg['v']);
            } elseif ($cfg['cat'] === 'card' && $cfg['k'] === 'deck') {
                $deck = $cfg['v'];
            } elseif ($cfg['cat'] === 'card' && $cfg['k'] === 'template') {
                $template = $cfg['v'];
            }
        }

        return [$slug, $deck, $template];
    }

    /** Short plain-text teaser: the summary when set, else a body excerpt. */
    private static function cardTeaser(array $item): string
    {
        $src = trim(Response::decodeEntities($item['summary'] ?? ''));
        if (!$src) {
            $src = trim((string) ($item['body'] ?? ''));
            // Drop bbcode tags rather than render them — this is an attribute
            // value, not markup.
            $src = preg_replace('/\[[^\]]{0,60}\]/', '', $src) ?? $src;
        }
        $src = trim(preg_replace('/\s+/', ' ', $src) ?? $src);

        return mb_strlen($src) > 200 ? mb_substr($src, 0, 200) . '…' : $src;
    }

    /** First image in the body, if any — used as the card's cover. */
    private static function cardCover(array $item): string
    {
        $body = (string) ($item['body'] ?? '');
        if (preg_match('/\[z?img[^\]]*\](.*?)\[\/z?img\]/is', $body, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
