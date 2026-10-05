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

        $quote = Activity::get_quote($m[1]);   // ASCache'd, so N local recipients fetch once
        if (!empty($quote['bbcode'])) {
            $arr['body'] = Activity::pasteQuote($body, $quote);
        }
    }
}
