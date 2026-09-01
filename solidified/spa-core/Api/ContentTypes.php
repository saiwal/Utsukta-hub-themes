<?php
// Api/ContentTypes.php
namespace Utsukta\SpaCore\Api;

use Zotlabs\Lib\MarkdownSoap;

// Hubzilla stores an item's body raw in its authoring format and records that
// format in item.mimetype; nothing is ever converted between formats. Core
// keeps the list of valid formats in three places that have drifted apart
// (include/text.php mimetype_select(), include/import.php, and the wiki
// addon). Keep the SPA's copy in exactly one place: here, and its TypeScript
// twin in packages/spa-core/src/lib/mimetypes.ts.
final class ContentTypes
{
    // What the SPA lets a client author. Excludes application/x-php (eval'd
    // server-side at render time, gated on channel_codeallowed) and
    // application/x-pdl (Comanche layouts, which the SPA replaced with widget
    // templates). Items already stored with either still render.
    public const AUTHORABLE = [
        'text/bbcode',
        'text/html',
        'text/markdown',
        'text/plain',
    ];

    // The wiki addon offers its own narrower list (Mod_Wiki.php:221).
    public const WIKI = [
        'text/markdown',
        'text/bbcode',
        'text/plain',
    ];

    // Formats a post or comment may be *authored* in. Unlike everywhere else
    // these are input formats only — toBbcode() converts before storage, so
    // item.mimetype always ends up text/bbcode on a post. See toBbcode().
    public const POST = [
        'text/bbcode',
        'text/markdown',
    ];

    // Client-supplied mimetype → a whitelisted value. Unknown values fall back
    // rather than erroring, matching item_store()'s tolerance; note that core's
    // Zotlabs\Module\Item does no whitelisting at all, so this is stricter.
    public static function validate(?string $mimetype, ?array $allowed = null, string $fallback = 'text/bbcode'): string
    {
        $mimetype = trim((string) $mimetype);

        return in_array($mimetype, $allowed ?? self::AUTHORABLE, true) ? $mimetype : $fallback;
    }

    // Decode a body for reading (display or edit form).
    //
    // z_input_filter() stores text/markdown through MarkdownSoap->clean(),
    // which purifies *and* htmlspecialchars-escapes the source. Every read path
    // must reverse that escaping or the client sees `&lt;` and `&quot;` instead
    // of the markdown the author typed. Core does this in Editwebpage.php:129
    // and Editblock.php:102, and inside prepare_text() for display; it forgets
    // to in Editpost.php:94, which is an upstream bug, not a convention.
    //
    // No other format needs decoding: bbcode/plain keep their escape_tags()
    // form (bbcode.ts unescapes as it parses), and text/html is stored as
    // already-purified HTML.
    public static function decode(?string $body, ?string $mimetype): string
    {
        $body = (string) $body;

        return ($mimetype === 'text/markdown') ? MarkdownSoap::unescape($body) : $body;
    }

    // Normalise a post/comment body to bbcode. Returns [$body, $mimetype].
    //
    // Posts federate, and only text/bbcode survives the trip: Activity.php:717
    // emits `content`/`source` for bbcode alone, so a stored-markdown post
    // ships an empty body to remote followers; Lib/Share.php:35 refuses to
    // reshare one; and Zotlabs\Module\Item:610 keeps cleanup_bbcode() and
    // linkify_tags() inside its bbcode branch, so it would get no hashtags or
    // mentions either. So markdown is an *input* format on posts, not a
    // storage format — convert up front and store bbcode, exactly as core does
    // for inbound federated markdown (Activity.php:2392).
    //
    // Call this BEFORE the caller's `if ($mimetype === 'text/bbcode')` block so
    // the converted body still gets tag linkification and its term rows.
    //
    // The mdpost addon does the same job for core's composer, from a
    // 'post_content' hook that only Zotlabs\Module\Item fires — this handler
    // does not, so there is no double conversion. What the SPA does share with
    // it is the toggle: composing in Markdown is gated on mdpost's own
    // 'markdown' feature rather than a second SPA-specific one.
    //
    // Only for posts and comments. Webpages, blocks, articles, cards and wiki
    // pages are local content that never federates, so they keep their real
    // mimetype and round-trip losslessly.
    public static function toBbcode(string $body, string $mimetype): array
    {
        if ($mimetype !== 'text/markdown') {
            return [$body, $mimetype];
        }

        require_once('include/markdown.php');

        // preserve_lf: without it a single newline collapses into a space
        // ("line one\nline two" -> "line one line two"). That is correct
        // Markdown, but wrong here — bbcode preserves newlines and anyone
        // typing into a Hubzilla composer expects that.
        return [markdown_to_bb(self::gfmToBbcode($body), false, ['preserve_lf' => true]), 'text/bbcode'];
    }

    // GitHub-flavoured Markdown that PHP Markdown Extra does not know, mapped
    // to the bbcode that means the same thing.
    //
    // markdown_to_bb() runs MarkdownExtra, which has no strikethrough and no
    // task lists — "~~x~~" would survive as literal tildes and "- [ ] a" as a
    // list item reading "[ ] a". Both exist in bbcode ([s] and [checklist],
    // include/bbcode.php:1399 and :1546), and bbcode passes through
    // markdown_to_bb() untouched, so translating first is all it takes.
    //
    // The editor accepts both because marked parses GFM, so without this the
    // composer would show something the posted item does not.
    private static function gfmToBbcode(string $md): string
    {
        // ~~struck~~ -> [s]struck[/s]. Not inside a fenced or inline code span.
        $md = preg_replace_callback(
            '/(```[\s\S]*?```|`[^`\n]*`)|~~(?!\s)(.+?)(?<!\s)~~/s',
            fn(array $m) => $m[1] !== '' ? $m[1] : '[s]' . $m[2] . '[/s]',
            $md
        );

        // A run of "- [ ] a" / "- [x] b" lines -> one [checklist] block, whose
        // item markers are [] and [x] (bb_checklist in include/bbcode.php).
        return preg_replace_callback(
            '/(?:^[ \t]*[-*+][ \t]+\[[ xX]\][ \t]*.*(?:\n|$))+/m',
            function (array $m): string {
                $items = [];
                foreach (preg_split('/\n/', rtrim($m[0], "\n")) as $line) {
                    if (!preg_match('/^[ \t]*[-*+][ \t]+\[([ xX])\][ \t]*(.*)$/', $line, $p)) continue;
                    $items[] = (strtolower($p[1]) === 'x' ? '[x] ' : '[] ') . $p[2];
                }
                return $items ? "[checklist]\n" . implode("\n", $items) . "\n[/checklist]\n" : $m[0];
            },
            $md
        );
    }

    // ── Remembering the Markdown an item was written in ────────────────────
    //
    // toBbcode() is one-way, so without this a post composed in Markdown
    // reopens for editing as the converted bbcode. Keep the original beside
    // the item (iconfig cat 'spa', the same place local_only lives) so the
    // edit composer can hand back what the author actually typed.
    //
    // The hash guards against the source going stale: if the item is later
    // edited somewhere else — the classic UI, a clone, an addon — item.body no
    // longer corresponds to this Markdown, and restoring it would silently
    // revert that edit. Mismatch means fall back to bbcode.

    public static function rememberMarkdown(int $iid, string $markdown, string $bbcode): void
    {
        set_iconfig($iid, 'spa', 'md_source', $markdown);
        set_iconfig($iid, 'spa', 'md_hash', sha1($bbcode));
    }

    /** Drop a stored source — call when an item is saved as anything but Markdown. */
    public static function forgetMarkdown(int $iid): void
    {
        del_iconfig($iid, 'spa', 'md_source');
        del_iconfig($iid, 'spa', 'md_hash');
    }

    /** The Markdown an item was written in, or '' if there is none or it is stale. */
    public static function recallMarkdown(int $iid, string $currentBody): string
    {
        $src = get_iconfig($iid, 'spa', 'md_source');
        if (!is_string($src) || $src === '') {
            return '';
        }

        $hash = get_iconfig($iid, 'spa', 'md_hash');
        return (is_string($hash) && hash_equals($hash, sha1($currentBody))) ? $src : '';
    }
}
