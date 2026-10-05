<?php
// Api/QuoteIngest.php
namespace Utsukta\SpaCore\Api;

use Utsukta\SpaCore\Api\Concerns\ValidatesRemoteHost;
use Zotlabs\Lib\Activity;

/**
 * Paste the quoted post into a zot-delivered quote-post before it is stored.
 *
 * Hubzilla sends a quote as a bare "RE: <url>" line. Core turns that into a
 * [share] block only on ActivityPub ingest (Activity::decode_note ->
 * get_quote/pasteQuote); a zot copy is stored with the bare line, and redbasic
 * papers over it with a render-time oembed. This runs the same two core calls
 * from the post_remote / post_remote_update hooks, so the stored body carries
 * the block and every renderer shows it. FormatsItems::pasteLocalQuote stays
 * as the render-time fallback for rows stored before this hook existed.
 */
class QuoteIngest
{
    use ValidatesRemoteHost;

    /** post_remote / post_remote_update — $arr is the item about to be written. */
    public static function onPostRemote(array &$arr): void
    {
        $body = (string) ($arr['body'] ?? '');

        if (intval($arr['item_origin'] ?? 0)
            || !in_array($arr['mimetype'] ?? '', ['', 'text/bbcode'], true)
            || str_contains($body, '[/share]')
            || !preg_match('/RE:\s*(?:\[url=[^\]]*\])?(https?:\/\/[^\s\[]+)/i', $body, $m)) {
            return;
        }

        // The url is author-supplied, so don't let it point the fetch at the
        // hub's own network. ponytail: resolve-then-fetch leaves a DNS-rebind
        // window; core's AP quote path fetches with no check at all.
        $host = parse_url($m[1], PHP_URL_HOST);
        if (!$host || !self::resolveSafePublicIp($host)) {
            return;
        }

        // ASCache'd, so N local recipients fetch once. The origin may refuse
        // the quoted post outright (hub.hubzilla.hu 404s some before any
        // signature check), so fall back to what the sender rendered.
        $quote = Activity::get_quote($m[1]) ?: self::quoteFromObj($arr['obj'] ?? null, $m[1]);
        if (!empty($quote['bbcode'])) {
            $arr['body'] = Activity::pasteQuote($body, $quote);
        }
    }

    /**
     * The quote rebuilt from the sender's own rendering: a Hubzilla sender's
     * `content` HTML already carries the quoted post as a shared_container
     * div, and core keeps that object in item.obj. Returns get_quote()'s
     * shape, or [] when there is no such div.
     *
     * ponytail: scrapes core's share markup (include/bbcode.php
     * bb_ShareAttributes); a markup change there makes this return [] and the
     * post falls back to the bare link, nothing worse.
     */
    public static function quoteFromObj(mixed $obj, string $url): array
    {
        $obj  = (new \Zotlabs\Lib\ASObjectStorage($obj))->decode();
        $html = is_array($obj) && is_string($obj['content'] ?? null) ? $obj['content'] : '';
        if (!str_contains($html, 'shared_container')) {
            return [];
        }

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET);
        $xp  = new \DOMXPath($doc);
        $cls = fn(string $c) => "contains(concat(' ', normalize-space(@class), ' '), ' $c ')";

        $content = $xp->query("//div[{$cls('reshared-content')}]")->item(0);
        $header  = $xp->query("//div[{$cls('shared_header')}]")->item(0);
        if (!$content || !$header) {
            return [];
        }

        $img     = $xp->query('.//img', $header)->item(0);
        $profile = $xp->query('.//a', $header)->item(0)?->getAttribute('href') ?? '';
        $posted  = $xp->query(".//span[{$cls('autotime')}]", $header)->item(0)?->getAttribute('title') ?? '';
        $inner   = '';
        foreach ($content->childNodes as $n) {
            $inner .= $doc->saveHTML($n);
        }

        // Remote-supplied attribute values: a ' or ] would end the attribute
        // (or the tag) early and let the sender inject attributes.
        $a = fn(?string $v) => str_replace(["'", ']', '['], '', (string) $v);

        require_once 'include/html2bbcode.php';
        $bb = "[share author='" . urlencode($img?->getAttribute('alt') ?? '') .
            "' profile='" . $a($profile) .
            "' avatar='" . $a($img?->getAttribute('src')) .
            "' link='" . $a($url) .
            "' auth='false' posted='" . ($posted ? datetime_convert('UTC', 'UTC', $posted) : '') .
            "' message_id='" . $a($url) . "']" . html2bbcode($inner) . '[/share]';

        return ['bbcode' => $bb, 'url' => $url, 'mid' => $url];
    }
}
