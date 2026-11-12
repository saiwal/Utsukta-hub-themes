<?php
namespace Utsukta\SpaCore\Api\Handlers;

use App;
use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Cache;
use Zotlabs\Lib\Config;

/**
 * POST /spa/share/email
 *
 * Body: { to, url, title?, note? }
 *
 * Emails a link to something on this hub. Delivery goes through core's single
 * mail path, z_mail() → Zotlabs\Lib\Mailer, which fires the `email_send` hook —
 * so a site with the `phpmailer` addon enabled gets full SMTP for free, with
 * credentials held site-side in config family `phpmailer`. Nothing per-user is
 * stored: the message is From the site's configured address with the sharing
 * user's own account email as Reply-To.
 *
 * This endpoint lets an authenticated user make our server send text to an
 * arbitrary address, so it is deliberately fenced: local channel only, CSRF
 * enforced, same-origin URLs only, recipients capped, note length-capped and
 * never interpolated into a header, and throttled per uid.
 */
class Share
{
    private const MAX_RECIPIENTS = 5;
    private const MAX_NOTE_LEN = 1000;
    // Max share emails one channel may send within the window below.
    private const MAX_REQUESTS = 20;
    private const REQUEST_WINDOW = '1 HOUR';

    public function post(): void
    {
        $uid = Auth::requireLocalJson();

        if ((\App::$argv[2] ?? '') !== 'email') {
            Response::error(404, 'Not found');
        }

        $body = Auth::$parsedBody;

        $recipients = $this->parseRecipients((string) ($body['to'] ?? ''));
        $url = trim((string) ($body['url'] ?? ''));
        $title = \notags(trim((string) ($body['title'] ?? '')));
        $note = \notags(trim((string) ($body['note'] ?? '')));

        // Same-origin only. Without this the endpoint is an open relay for
        // pointing spam at any URL from a trusted hub address.
        $root = \z_root();
        if ($url === '' || strpos($url, $root . '/') !== 0) {
            Response::error(400, 'Link must point at this site');
        }

        if (mb_strlen($note) > self::MAX_NOTE_LEN) {
            Response::error(400, 'Note is too long');
        }

        // Optional guest-access token. Appended here rather than accepting a
        // client-supplied full URL, so the same-origin check above still
        // governs where the link points. The token must be one of this
        // channel's own and not expired — never a value echoed back to us.
        $zat = trim((string) ($body['zat'] ?? ''));
        $guestLines = [];
        if ($zat !== '') {
            $t = q("SELECT atoken_name, atoken_expires FROM atoken WHERE atoken_uid = %d AND atoken_token = '%s' LIMIT 1",
                intval($uid),
                dbesc($zat)
            );
            if (!$t) {
                Response::error(400, 'Unknown guest token');
            }
            $expires = $t[0]['atoken_expires'];
            if ($expires > \DBA::$dba->get_null_date() && strtotime($expires . 'Z') < time()) {
                Response::error(400, 'That guest token has expired');
            }
            $url .= (str_contains($url, '?') ? '&' : '?') . 'zat=' . rawurlencode($zat);

            // Say what the link is. Without this the recipient gets an opaque
            // URL and no idea it is a credential that stops working one day.
            $guestLines[] = sprintf(
                'This link signs you in as guest "%s" - treat it like a password.',
                $t[0]['atoken_name']
            );
            if ($expires > \DBA::$dba->get_null_date()) {
                $guestLines[] = 'Guest access expires: ' . $expires . ' UTC';
            }
        }

        $throttleKey = 'spa_share_email:' . $uid;
        $count = (int) Cache::get($throttleKey, self::REQUEST_WINDOW);
        if ($count + count($recipients) > self::MAX_REQUESTS) {
            Response::error(429, 'Too many share emails sent. Please try again later.');
        }
        Cache::set($throttleKey, (string) ($count + count($recipients)));

        $channel = \App::get_channel();
        $sender = $channel['channel_name'] ?? ($channel['channel_address'] ?? '');
        $siteName = Config::Get('system', 'sitename');

        // Fixed subject — no user text reaches the header, so there is nothing
        // to inject with.
        $subject = sprintf('%s shared a link with you', $sender ?: $siteName);

        $lines = [sprintf('%s shared this with you on %s.', $sender, $siteName), ''];
        if ($title !== '') {
            $lines[] = $title;
        }
        $lines[] = $url;
        foreach ($guestLines as $gl) {
            $lines[] = $gl;
        }
        if ($note !== '') {
            $lines[] = '';
            $lines[] = $note;
        }
        $text = implode("\n", $lines) . "\n";

        $replyTo = \App::$account['account_email'] ?? '';

        $sent = 0;
        foreach ($recipients as $to) {
            $params = [
                'toEmail' => $to,
                'messageSubject' => \email_header_encode($subject, 'UTF-8'),
                'textVersion' => $text,
            ];
            if ($replyTo) {
                $params['replyTo'] = $replyTo;
            }
            if (\z_mail($params)) {
                $sent++;
            }
        }

        if (!$sent) {
            Response::error(502, 'Message delivery failed');
        }

        Response::send(['sent' => $sent]);
    }

    /**
     * Splits a comma-separated recipient string, validating each address.
     * Errors out rather than silently dropping — a share the user thinks went
     * out but didn't is worse than a visible rejection.
     *
     * @return string[]
     */
    private function parseRecipients(string $raw): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));

        if (!$parts) {
            Response::error(400, 'Recipient email address is required');
        }
        if (count($parts) > self::MAX_RECIPIENTS) {
            Response::error(400, sprintf('At most %d recipients', self::MAX_RECIPIENTS));
        }
        foreach ($parts as $addr) {
            if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                Response::error(400, 'Invalid email address: ' . $addr);
            }
        }

        return $parts;
    }
}
