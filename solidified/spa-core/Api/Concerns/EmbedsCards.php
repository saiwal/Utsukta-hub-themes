<?php
namespace Utsukta\SpaCore\Api\Concerns;

use Utsukta\SpaCore\Api\Response;

/**
 * The [card] embed: a compact [card=<item id>][/card] token the composer
 * inserts, expanded at save time into a stored block and collapsed back to the
 * compact form when an item is reopened for editing.
 *
 * The stored form is a plain [share …]…[/share] block whose link points at
 * /cards/<nick>/<slug>. That is deliberate: a bespoke [card …] block rendered
 * as literal bracket text in every theme but this one. Both core
 * (include/bbcode.php bb_ShareAttributes) and the SPA (bbcode.ts
 * bbShareAttributes) already special-case a share link containing "/cards/"
 * and label the block a card, so reusing the share format renders correctly in
 * redbasic and over federation for free — the same portability argument that
 * put the authoring templates on core bbcode.
 *
 * Only the compact [card=<id>] token is card-specific, and it never survives a
 * save. Collapsing back is handled by Item::collapseShareTags, which emits
 * [card=<id>] when the block resolves to an ITEM_TYPE_CARD — one scanner for
 * both, since a stored card embed *is* a share block.
 *
 * Shared by Item.php (posts and comments) and Handlers\Cards (a card body
 * embedding another card).
 */
trait EmbedsCards
{
    /**
     * Build the stored [share …]…[/share] block for a card.
     *
     * Mirrors Item::buildShareBlock, including its escaping convention — core
     * urldecodes only `author`, so every other attribute goes in raw — and its
     * $forDisplay flag: composer previews may render a private card the viewer
     * can already see, save-time expansion may not embed one that isn't theirs.
     */
    protected function buildCardBlock(array $item, bool $forDisplay = false): string
    {
        if ($item['mimetype'] !== 'text/bbcode') {
            return '';
        }

        // ponytail: owner-only gate on private cards — looser than [share],
        // which refuses item_private outright. A private card embedded into a
        // more widely addressed post puts its content in front of a larger
        // audience than the card's own ACL, and travels on with any reshare.
        // Upgrade path: compare the host post's ACL against the card's here
        // and refuse on widening. Item::collapseShareTags applies the same
        // rule, so the two directions agree about what is embeddable.
        if (intval($item['item_private']) && !$forDisplay
            && intval($item['uid']) !== intval(local_channel())) {
            return '';
        }

        $link = self::appItemLink($item);
        if (!$link) {
            return '';
        }

        $rows = [$item];
        xchan_query($rows, true);
        $author  = $rows[0]['author'] ?? [];
        $network = $author['xchan_network'] ?? '';

        $bb  = "[share author='" . urlencode($author['xchan_name'] ?? '') . "'\n";
        $bb .= "\tprofile='" . ($author['xchan_url'] ?? '') . "'\n";
        $bb .= "\tavatar='" . ($author['xchan_photo_s'] ?? '') . "'\n";
        // The card's app URL, not its plink — this is what makes both bbcode
        // renderers label the block a card rather than a post.
        $bb .= "\tlink='" . $link . "'\n";
        $bb .= "\tauth='" . ($network === 'zot6' ? 'true' : 'false') . "'\n";
        $bb .= "\tposted='" . ($item['created'] ?? '') . "'\n";
        $bb .= "\tmessage_id='" . ($item['mid'] ?? '') . "'\n";
        $bb .= ']';

        if ($item['title']) {
            $bb .= '[h3][b]' . $item['title'] . '[/b][/h3]' . "\r\n";
        }

        $bb .= $item['body'];
        $bb .= '[/share]';

        return $bb;
    }

    /**
     * Expand compact [card=<item id>][/card] tags into the stored block before
     * storing — the same mechanism (and the same 422-on-failure discipline) as
     * Item::expandShareTags.
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
     * Whether this item's [share] block should link to its app page rather
     * than its plink. Keeps the type list in one place beside appItemLink().
     */
    protected static function isAppItem(array $item): bool
    {
        return in_array(intval($item['item_type'] ?? 0),
            [ITEM_TYPE_CARD, ITEM_TYPE_ARTICLE], true);
    }

    /**
     * A shareable app item's human-facing URL — /cards/<nick>/<slug-or-uuid>
     * or /articles/<nick>/<slug-or-uuid> — or '' for an ordinary post (whose
     * plink already is its display URL) or when the owning channel can't be
     * resolved. Distinct from the item's plink, which is the mid-based
     * federation identity: this is the attribute both bbcode renderers read to
     * label a [share] block a card or an article rather than a post
     * (include/bbcode.php bb_ShareAttributes, bbcode.ts bbShareAttributes).
     */
    protected static function appItemLink(array $item): string
    {
        $type = intval($item['item_type'] ?? 0);
        $seg = match ($type) {
            ITEM_TYPE_CARD    => 'cards',
            ITEM_TYPE_ARTICLE => 'articles',
            default           => '',
        };
        if (!$seg) {
            return '';
        }

        $c = q("SELECT channel_address FROM channel WHERE channel_id = %d LIMIT 1",
            intval($item['uid']));
        if (!$c || !$c[0]['channel_address']) {
            return '';
        }

        $slug = '';
        $cfg = q("SELECT v FROM iconfig WHERE iid = %d AND cat = 'system' AND k = '%s' LIMIT 1",
            intval($item['id']), dbesc(item_type_to_namespace($type)));
        if ($cfg) {
            $slug = urldecode($cfg[0]['v']);
        }

        return z_root() . '/' . $seg . '/' . $c[0]['channel_address'] . '/' . ($slug ?: $item['uuid']);
    }
}
