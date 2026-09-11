<?php
namespace Utsukta\SpaCore\Api\Concerns;

use Utsukta\SpaCore\Api\Response;

/**
 * The [share] and [card] embeds.
 *
 * The composer inserts a compact token — [share=<item id>][/share] or
 * [card=<item id>][/card] — which is expanded at save time into a stored
 * block, and collapsed back to the compact form when an item is reopened for
 * editing (Item::collapseShareTags, which stays there because it resolves its
 * target by mid through Item::resolveItem).
 *
 * Both tags store the *same* thing: a plain [share …]…[/share] block. For a
 * card the block's link points at /cards/<nick>/<slug> instead of a plink,
 * and that is the only difference. It is deliberate — a bespoke [card …]
 * block rendered as literal bracket text in every theme but this one, while
 * both core (include/bbcode.php bb_ShareAttributes) and the SPA (bbcode.ts
 * bbShareAttributes) already special-case a share link containing "/cards/"
 * and label the block a card. Reusing the share format renders correctly in
 * redbasic and over federation for free — the same portability argument that
 * put the authoring templates on core bbcode.
 *
 * Because the stored form is shared, one builder serves both, and the two
 * directions cannot drift apart about what is embeddable. They used to be
 * separate copies (Item::buildShareBlock and buildCardBlock) that each
 * carried a comment telling the reader to keep them in agreement.
 *
 * Used by Item.php (posts and comments) and Handlers\Cards (a card body
 * embedding another card).
 */
trait EmbedsItems
{
    /**
     * Build the stored [share …]…[/share] block for an item.
     *
     * Escaping follows core's convention: it urldecodes only `author`, so
     * every other attribute goes in raw.
     *
     * @param bool $forDisplay   Composer previews may render a private item
     *                           the viewer can already see; save-time
     *                           expansion must never embed one, or the block
     *                           travels to everyone the host post reaches.
     * @param bool $ownPrivateOk Keyed on the *tag*, not the item: [card]
     *                           additionally allows the owner's own private
     *                           card, [share] refuses item_private outright.
     *                           Without this, [share=<card id>] would inherit
     *                           the looser rule and widen a card's audience.
     */
    protected function buildEmbedBlock(array $item, bool $forDisplay = false, bool $ownPrivateOk = false): string
    {
        if (($item['mimetype'] ?? '') !== 'text/bbcode') {
            return '';
        }

        $isCard = intval($item['item_type'] ?? 0) === ITEM_TYPE_CARD;

        // ownPrivateOk belongs to the [card] tag, so it may only ever relax a
        // card. Re-deriving it from the row rather than trusting the flag
        // keeps a future caller from widening a private post's audience by
        // passing the card path a post id.
        $ownPrivateOk = $ownPrivateOk && $isCard;

        // ponytail: owner-only gate on private cards — looser than [share],
        // which refuses item_private outright. A private card embedded into a
        // more widely addressed post puts its content in front of a larger
        // audience than the card's own ACL, and travels on with any reshare.
        // Upgrade path: compare the host post's ACL against the card's here
        // and refuse on widening. Item::collapseShareTags applies the same
        // rule, so the two directions agree about what is embeddable.
        if (intval($item['item_private']) && !$forDisplay
            && !($ownPrivateOk && intval($item['uid']) === intval(local_channel()))) {
            return '';
        }

        // App items (articles, cards) link to their own page, not their plink:
        // that is what makes both bbcode renderers label the block correctly.
        $appLink = self::appItemLink($item);

        // A card is only *recognisable* as a card by that URL, so a card whose
        // owning channel can't be resolved has no usable block at all — the
        // plink fallback would silently render it as an ordinary post.
        if ($isCard && !$appLink) {
            return '';
        }

        $rows = [$item];
        xchan_query($rows, true);
        $author  = $rows[0]['author'] ?? [];
        $network = $author['xchan_network'] ?? '';

        // quote='true' tells Activity::encode_item to strip the block and
        // federate it as quoteUrl = the block's link attribute (Lib/Activity.php
        // ~677). That only works when the link is an AS-resolvable object, i.e.
        // an ordinary post's plink. An app item's link is its HTML app page, so
        // quoting it federates as an unfetchable "RE: <url>" and the remote
        // renders bare text — send the block inline instead.
        $quote = (!self::isAppItem($item) && in_array($network, ['zot6', 'activitypub']))
            ? "quote='true'"
            : '';

        $bb  = "[share author='" . urlencode($author['xchan_name'] ?? '') . "'\n";
        $bb .= "\tprofile='" . ($author['xchan_url'] ?? '') . "'\n";
        $bb .= "\tavatar='" . ($author['xchan_photo_s'] ?? '') . "'\n";
        $bb .= "\tlink='" . ($appLink ?: ($item['plink'] ?? '')) . "'\n";
        $bb .= "\tauth='" . ($network === 'zot6' ? 'true' : 'false') . "'\n";
        $bb .= "\tposted='" . ($item['created'] ?? '') . "'\n";
        $bb .= "\tmessage_id='" . ($item['mid'] ?? '') . "'\n";
        if ($quote) {
            $bb .= "\t$quote\n";
        }
        $bb .= ']';

        if ($item['title']) {
            $bb .= '[h3][b]' . $item['title'] . '[/b][/h3]' . "\r\n";
        }

        $bb .= $item['body'];
        $bb .= '[/share]';

        return $bb;
    }

    // -------------------------------------------------------------------------
    // Backlink index (iconfig cat 'spa', key 'embeds')
    //
    // "What embeds this card?" used to be answered by a LIKE '%message_id=…%'
    // over every body in the channel — which on a real hub means every post
    // ever delivered to it, so opening one card read tens of thousands of
    // bodies. Recording the embedded mids at save time turns that into an
    // indexed lookup on iconfig.k, over only the rows that actually are
    // embeds.
    //
    // ponytail: the lookup is an equality match on iconfig.k plus a LIKE over
    // the matched rows' v, so its cost tracks the number of embeds on the
    // *hub*, not the number pointing at this card. Rows are ~100 bytes and the
    // index is hot, so that is microseconds at hub scale and well below the
    // cost of the thread query next to it. If hub-wide embeds ever reach five
    // figures: move the target mid into the key (k = 'embeds:<sha1(mid)>', one
    // row per referenced mid, mid mirrored in v so the rows stay readable) and
    // the LIKE becomes an exact index hit. The catch is the writer, not the
    // reader — buildEditDatarray() pre-loads every iconfig row, so the edit
    // path would have to strip stale 'embeds:*' entries from that array by
    // hand instead of calling IConfig::Delete once.
    //
    // The list is re-derived from the *stored* body on every save rather than
    // tracked alongside the expansion, so it can't drift: whatever share
    // blocks the body ends up with are what gets indexed, whichever path
    // (compose, edit, reshare) produced them. Nothing was backfilled, so an
    // embed written before this existed stays invisible to the backlink query
    // until its host item is next saved.
    // -------------------------------------------------------------------------

    /** Separator *and* terminator, so a LIKE can match a whole mid. See setEmbedIconfig(). */
    protected const EMBED_SEP = "\n";

    /**
     * The mids of every item embedded in a stored body.
     *
     * Matches the block's message_id attribute, not its link: the link carries
     * a slug and changes when an app item is renamed, the mid never does. A
     * nested block (an embedded item whose own body embeds something) is
     * picked up too — it is genuinely mentioned in this body.
     */
    protected static function embedRefs(string $body): array
    {
        if (!preg_match_all("/\[share\b[^\]]*?\bmessage_id='([^']*)'/is", $body, $m)) {
            return [];
        }

        return array_values(array_unique(array_filter($m[1])));
    }

    /**
     * Record (or clear) a body's embed backlinks.
     *
     * Stored as one iconfig row holding the mids wrapped in separators
     * ("\n<mid>\n<mid>\n"), because IConfig keys one row per cat+key and a
     * row per mid would need a different storage layer. The wrapping is what
     * lets the reader match a whole mid (LIKE '%\n<mid>\n%') instead of a
     * prefix.
     *
     * $target is by reference for the same reason setGroupIconfig() is: given
     * a datarray IConfig::Set appends to its 'iconfig' key for item_store() to
     * write, and by value the caller keeps an unmodified copy. Given an item
     * id it writes the row directly, which is what the paths that update the
     * item row themselves (Item::editItem) need.
     *
     * Clearing matters as much as setting: item_store_update() re-inserts only
     * what the datarray carries — but editItem() doesn't go through it, so an
     * edit that removes the last embed has to delete the row explicitly.
     */
    protected function setEmbedIconfig(&$target, string $body): void
    {
        $refs = self::embedRefs($body);

        if ($refs) {
            \Zotlabs\Lib\IConfig::Set($target, 'spa', 'embeds',
                self::EMBED_SEP . implode(self::EMBED_SEP, $refs) . self::EMBED_SEP);
        } elseif (!is_array($target) || isset($target['iconfig'])) {
            // IConfig::Delete() reads $target['iconfig'] unguarded, so a fresh
            // create datarray (no iconfig key yet, and nothing to clear) would
            // only earn an undefined-key warning.
            \Zotlabs\Lib\IConfig::Delete($target, 'spa', 'embeds');
        }
    }

    /**
     * SQL condition matching the iconfig rows (aliased $alias) that embed $mid.
     * Escapes LIKE's own wildcards before dbesc — a mid is a URL and '_' is
     * common in one.
     */
    protected static function embedMatchSql(string $mid, string $alias = 'e'): string
    {
        $needle = dbesc(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'],
            self::EMBED_SEP . $mid . self::EMBED_SEP));

        return "$alias.cat = 'spa' AND $alias.k = 'embeds' AND $alias.v LIKE '%$needle%'";
    }

    /**
     * Re-read a client-supplied numeric item id behind its owner's permission
     * SQL, so an id guessed by the caller cannot surface an item they are not
     * permitted to see. Returns null when the id doesn't exist or the viewer
     * fails the ACL.
     *
     * @param ?int $itemType When given, the id must be of that type and not
     *                       deleted — the [card] tag's constraint.
     */
    protected function permittedItemById(int $id, ?int $itemType = null): ?array
    {
        $typeSql = $itemType === null
            ? ''
            : ' AND item_type = ' . intval($itemType) . ' AND item_deleted = 0';

        $r = q("SELECT uid FROM item WHERE id = %d$typeSql LIMIT 1", $id);
        if (!$r) {
            return null;
        }

        $sql_extra = item_permissions_sql(intval($r[0]['uid']));
        $v = q("SELECT * FROM item WHERE id = %d $sql_extra", $id);

        return $v ? $v[0] : null;
    }

    /**
     * Expand compact [share=<item id>][/share] tags into the canonical
     * [share author=…]…[/share] block before storing — same mechanism as core
     * Item::post. Any content inside the compact tag is discarded, as core does.
     */
    protected function expandShareTags(string $body): string
    {
        if (!preg_match_all('/(\[share=(\d+)\](.*?)\[\/share\])/ism', $body, $match)) {
            return $body;
        }

        foreach ($match[2] as $i => $id) {
            $item = $this->permittedItemById(intval($id));

            // Core Share::bbcode() hardcodes the item's plink as the block's
            // link, which would render an article or card embed as a generic
            // post pointing at /item/<uuid>. App items skip it and build the
            // block below, where appItemLink() supplies the /articles/ or
            // /cards/ URL both bbcode renderers key off.
            $bb = ($item && self::isAppItem($item))
                ? ''
                : (new \Zotlabs\Lib\Share(intval($id)))->bbcode();

            // App items, and posts Share::bbcode() refuses because their body
            // already contains [/share] (nested reshares). Build the block
            // ourselves, with the same visibility rules Lib\Share applies.
            if (!$bb && $item) {
                $bb = $this->buildEmbedBlock($item);
            }

            if (!$bb) {
                // Silently dropping the tag would eat the reshared content on
                // save; refuse instead so the composer keeps the user's draft.
                Response::error(422, 'Shared post not found or cannot be reshared');
            }

            $body = str_replace($match[1][$i], $bb, $body);
        }

        return $body;
    }

    /**
     * Expand compact [card=<item id>][/card] tags — same mechanism and the
     * same 422-on-failure discipline as expandShareTags, but constrained to
     * cards and allowing the owner's own private ones.
     */
    protected function expandCardTags(string $body): string
    {
        if (!preg_match_all('/(\[card=(\d+)\](.*?)\[\/card\])/ism', $body, $match)) {
            return $body;
        }

        foreach ($match[2] as $i => $id) {
            $item = $this->permittedItemById(intval($id), ITEM_TYPE_CARD);
            $bb   = $item ? $this->buildEmbedBlock($item, ownPrivateOk: true) : '';

            if (!$bb) {
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
