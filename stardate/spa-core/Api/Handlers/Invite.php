<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
use App;

/**
 * Invite handler — email invitation codes to prospective members.
 *
 * GET  /spa/invite         → gates, quota counters, expiry defaults, rendered
 *                            email templates for every supported locale
 * POST /spa/invite/check   → dry run: validate the recipient list, send nothing
 * POST /spa/invite         → send the mails and store the register rows
 *
 * Ported from core's Zotlabs/Module/Invite.php (the "ZAI" module). The stored
 * shape is identical — a `register` row with reg_didx = 'i' and the code in
 * reg_hash — so an invite sent from here is redeemed by the same
 * Register/Regate path a classic invite uses, including the auto-connect to
 * the inviter that keys off reg_byc.
 *
 * Three deliberate differences from core, all narrowing behaviour:
 *
 *  1. Quotas are enforced on send. Core checks invitation_by_user in get()
 *     only and never enforces invitations_max_users at all, so a POST straight
 *     at the module bypasses both.
 *  2. `lang` and `style` are whitelisted against the discovered template list
 *     and the subject has CR/LF stripped. Core interpolates the client-supplied
 *     values into a get_intltext_template() filename and hands the subject to
 *     z_mail() as a mail header unfiltered.
 *  3. The language lane lists the locales this package ships a template for,
 *     which are the locales the SPA has a UI for — not core's translation set.
 *
 * The email templates are this package's own (php/view/lang/<lc>/), not core's.
 * Core's set is unusable and unfinished — its en/invite.material.tpl is an
 * empty file, its formal body trails off at "Disclaimer: ...", and its own
 * glob looks in view/<lc>/ while the files live in view/lang/<lc>/, so the
 * classic picker's lanes are always empty anyway. Shipping our own also keeps
 * the offered locales in step with the locales the SPA has a UI for, instead
 * of with whatever core happens to have translated.
 */
class Invite
{
    /** Placeholder core displays in the preview; never stored. */
    private const PREVIEW_CODE = 'INVITATE2020';

    private const DEFAULT_STYLE = 'casual';

    // ── GET ───────────────────────────────────────────────────────────────────

    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $this->requireApp($uid);
        $this->requireEnabled();

        $mine  = $this->countMine($uid);
        $admin = is_site_admin();
        $myMax = null;

        if (!$admin) {
            // 0 disables invitations by users entirely.
            $myMax = intval(Config::Get('system', 'invitation_by_user', 4));
            if (!$myMax)
                Response::error(403, t('Invites by users not enabled'));
            if ($mine >= $myMax)
                Response::error(403, t('You have no more invitations available'));
        }

        $observer = App::get_observer();
        if (!$observer)
            Response::error(403, t('Not on xchan'));

        $channel  = App::get_channel();
        $whoami   = $channel['channel_address'];
        $whereami = $observer['xchan_addr'];

        $templates = $this->discoverTemplates();
        if (!$templates)
            Response::error(500, t('Invite template') . ': ' . t('Not Found'));

        $lang = in_array(App::$language, array_keys($templates), true) ? App::$language : 'en';
        $due  = self::calcdue();

        Response::send([
            'quota'          => ['mine' => $mine, 'my_max' => $myMax],
            'site'           => [
                'used' => $this->countSite(),
                'max'  => $this->siteMax(),
            ],
            'expire'         => $due,
            'max_recipients' => $this->maxRecipients(),
            'whoami'         => $whoami,
            'whereami'       => $whereami,
            'default_lang'   => $lang,
            // $templates[$lang] is a *list* of style names, so the fallback is
            // its first element, not its first key.
            'default_style'  => in_array(self::DEFAULT_STYLE, $templates[$lang], true)
                ? self::DEFAULT_STYLE
                : $templates[$lang][0],
            'templates'      => $this->renderAll($templates, self::PREVIEW_CODE, $whoami, $whereami),
        ]);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $this->requireApp($uid);
        $this->requireEnabled();

        if ((App::$argv[2] ?? '') === 'check') {
            $this->check();
            return;
        }

        $this->send($uid);
    }

    /** Dry run — core's is_ajax() branch of post(): validate, send nothing. */
    private function check(): void
    {
        $recips = $this->recipients();
        $due    = self::calcdue($this->expiry());

        Response::send([
            'due'     => $due['due'],
            'results' => $this->validate($recips),
        ]);
    }

    private function send(int $uid): void
    {
        $policy = intval(Config::Get('system', 'register_policy'));
        if ($policy == REGISTER_CLOSED)
            Response::error(403, t('Register is closed'));

        $flags = ($policy == REGISTER_APPROVE) ? ACCOUNT_PENDING : 0;
        $flags = ($flags | intval(Config::Get('system', 'verify_email')));
        // Invitations always verify by email — the code itself is the proof.
        $flags |= ACCOUNT_UNVERIFIED;

        $recips  = $this->recipients();
        $results = $this->validate($recips);

        // Core aborts the whole batch if any single address is bad.
        foreach ($results as $r) {
            if (!$r['ok'])
                Response::send(['ok' => 0, 'ko' => count($recips), 'results' => $results]);
        }

        $this->enforceQuota($uid, count($recips));

        $templates = $this->discoverTemplates();
        $data      = Auth::$parsedBody;

        $lang  = (string) ($data['lang'] ?? '');
        $style = (string) ($data['style'] ?? '');
        if (!isset($templates[$lang]) || !in_array($style, $templates[$lang], true))
            Response::error(400, t('Invite template') . ': ' . t('Not Found'));

        // Subject becomes a mail header; a newline in it is header injection.
        $subject = trim(str_replace(["\r", "\n"], '', notags((string) ($data['subject'] ?? ''))));
        if (!$subject)
            $subject = t('Invitation');

        $mailtext = notags(trim((string) ($data['message'] ?? '')));

        $dur = self::calcdue($this->expiry());
        $due = t('Note, the invitation code is valid up to') . ' ' . $dur['due'];

        $channel  = App::get_channel();
        $account  = App::get_account();
        $observer = App::get_observer();

        $reonar = [
            'from'     => $account['account_email'],
            'date'     => datetime_convert(),
            'fromip'   => $_SERVER['REMOTE_ADDR'] ?? '',
            'subject'  => $subject,
            'lang'     => $lang,
            'tpl'      => $style,
            'whoami'   => $channel['channel_address'],
            'whereami' => $observer ? $observer['xchan_addr'] : '',
        ];

        $ok = $ko = 0;
        $out = [];

        foreach ($recips as $recip) {
            // 8 pronounceable letters + 4 digits. The 12-char shape is
            // load-bearing: Register::post() fast-paths on /^[a-z0-9]{12,12}$/.
            $invite_code = autoname(8) . rand(1000, 9999);

            $body = $this->renderBody($lang, $style, $invite_code, $reonar['whoami'], $reonar['whereami']);

            $reonar['due']         = $dur['due'];
            $reonar['to']          = $recip;
            $reonar['txtpersonal'] = $mailtext;
            $reonar['txttemplate'] = $body;

            $sent = z_mail([
                'toEmail'        => $recip,
                'fromName'       => ' ',
                'fromEmail'      => $account['account_email'],
                'messageSubject' => $subject,
                'textVersion'    => ($mailtext ? $mailtext . "\n\n" : '') . $body . "\n" . $due,
            ]);

            if (!$sent) {
                $ko++;
                $msg = sprintf(t('%s : Message delivery failed.'), $recip);
                $out[] = ['email' => $recip, 'ok' => false, 'message' => $msg];
            } else {
                $ok++;
                $msg = sprintf(t('To %s : Message delivery success.'), $recip);
                $out[] = ['email' => $recip, 'ok' => true, 'message' => $msg];

                q("INSERT INTO register ( reg_flags, reg_didx, reg_did2, reg_hash, reg_created, reg_startup, reg_expires, reg_email, reg_byc, reg_uid, reg_atip, reg_lang, reg_stuff )
                    VALUES ( %d, 'i', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, '%s', '%s', '%s' )",
                    intval($flags),
                    dbesc($recip),
                    dbesc($invite_code),
                    dbesc(datetime_convert()),
                    dbesc(datetime_convert()),
                    dbesc($dur['due']),
                    dbesc($recip),
                    intval($uid),
                    intval($account['account_id']),
                    dbesc($reonar['fromip']),
                    dbesc($lang),
                    dbesc(json_encode(['reon' => $reonar]))
                );
            }

            zar_log($msg . ' (a' . $account['account_id'] . ', c' . $uid . ', from:' . $reonar['from'] . ')');
        }

        Response::send([
            'ok'      => $ok,
            'ko'      => $ko,
            'results' => $out,
            'quota'   => ['mine' => $this->countMine($uid), 'my_max' => is_site_admin() ? null : intval(Config::Get('system', 'invitation_by_user', 4))],
            'site'    => ['used' => $this->countSite(), 'max' => $this->siteMax()],
        ]);
    }

    // ── Input ─────────────────────────────────────────────────────────────────

    /** @return string[] deduplicated, trimmed, non-empty */
    private function recipients(): array
    {
        $raw = Auth::$parsedBody['recipients'] ?? [];
        if (is_string($raw))
            $raw = explode("\n", $raw);
        if (!is_array($raw))
            $raw = [];

        return array_values(array_filter(array_unique(array_map(
            fn($r) => trim((string) $r), $raw
        ))));
    }

    /** "<n><unit>" for calcdue(), from the client's expiry pair. */
    private function expiry(): string
    {
        $e    = Auth::$parsedBody['expire'] ?? [];
        $n    = intval($e['n'] ?? 2);
        $unit = (string) ($e['unit'] ?? 'd');

        if ($n < 1 || $n > 99)
            $n = 2;
        if (!preg_match('/^[ihd]$/', $unit))
            $unit = 'd';

        return $n . $unit;
    }

    /**
     * Per-recipient validation, core's checks in core's order.
     *
     * @return array<int,array{email:string,ok:bool,message:string}>
     */
    private function validate(array $recips): array
    {
        $max = $this->maxRecipients();

        if (count($recips) === 0)
            Response::error(400, t('No recipients for this invitation'));

        if (count($recips) > $max)
            Response::error(400, sprintf(t('Too many recipients for one invitation (max %d)'), $max));

        $out = [];
        foreach ($recips as $recip) {
            if (!validate_email($recip)) {
                $out[] = ['email' => $recip, 'ok' => false, 'message' => sprintf(t('(%s) : Not a real email address'), $recip)];
                continue;
            }
            if (!allowed_email($recip)) {
                $out[] = ['email' => $recip, 'ok' => false, 'message' => sprintf(t('(%s) : Not allowed email address'), $recip)];
                continue;
            }
            $r = q("SELECT account_email AS em FROM account WHERE account_email = '%s'
                     UNION
                    SELECT reg_email AS em FROM register WHERE reg_vital = 1 AND reg_email = '%s' LIMIT 1",
                dbesc($recip), dbesc($recip)
            );
            if ($r && $r[0]['em'] == $recip) {
                $out[] = ['email' => $recip, 'ok' => false, 'message' => sprintf(t('(%s) : email address already in use'), $recip)];
                continue;
            }
            $out[] = ['email' => $recip, 'ok' => true, 'message' => sprintf(t('(%s) : Accepted email address'), $recip)];
        }

        return $out;
    }

    // ── Gates and quotas ──────────────────────────────────────────────────────

    private function requireApp(int $uid): void
    {
        // Must be the exact .apd name: system_app_installed() hashes it.
        if (!Apps::system_app_installed($uid, 'Invite'))
            Response::error(403, t('Invite App') . ' (' . t('Not Installed') . ')');
    }

    private function requireEnabled(): void
    {
        if (!(Config::Get('system', 'invitation_also') || Config::Get('system', 'invitation_only')))
            Response::error(403, t('Invites not proposed by configuration') . '. ' . t('Contact the site admin'));
    }

    /** Core only checks these on page load, so a POST slipped past both. */
    private function enforceQuota(int $uid, int $count): void
    {
        if (!is_site_admin()) {
            $myMax = intval(Config::Get('system', 'invitation_by_user', 4));
            if (!$myMax)
                Response::error(403, t('Invites by users not enabled'));
            if ($this->countMine($uid) + $count > $myMax)
                Response::error(403, t('You have no more invitations available'));
        }

        if ($this->countSite() + $count > $this->siteMax())
            Response::error(403, t('Invitation limit exceeded. Please contact your site administrator.'));
    }

    private function countMine(int $uid): int
    {
        $r = q("SELECT count(reg_id) AS n FROM register WHERE reg_vital = 1 AND reg_byc = %d", intval($uid));
        return $r ? intval($r[0]['n']) : 0;
    }

    private function countSite(): int
    {
        $r = q("SELECT count(reg_id) AS n FROM register WHERE reg_vital = 1");
        return $r ? intval($r[0]['n']) : 0;
    }

    private function siteMax(): int
    {
        return intval(Config::Get('system', 'invitations_max_users')) ?: 50;
    }

    private function maxRecipients(): int
    {
        return intval(Config::Get('system', 'invitation_max_recipients')) ?: 12;
    }

    // ── Templates ─────────────────────────────────────────────────────────────

    /**
     * Locale → list of available styles, e.g. ['en' => ['casual','formal']].
     * Only locales the SPA ships are offered; a `.subject.tpl` is a companion
     * of a style, never a style of its own.
     *
     * @return array<string,string[]>
     */
    private function discoverTemplates(): array
    {
        $found = [];
        foreach (glob($this->packageRoot() . 'view/lang/*/invite.*.tpl') ?: [] as $path) {
            $lang = basename(dirname($path));
            $name = basename($path);
            if (str_contains($name, '.subject.'))
                continue;
            $found[$lang][] = str_replace(['invite.', '.tpl'], '', $name);
        }

        foreach ($found as $lang => $styles) {
            sort($styles);
            $found[$lang] = array_values(array_unique($styles));
        }
        ksort($found);

        return $found;
    }

    /**
     * This package's deployed root — <theme>/spa-core, and the only root the
     * templates are read from. Its whole php/ tree is copied there by the
     * build, so view/lang/<lc>/ rides along with no extra deploy step, and
     * every theme using spa-core gets the same templates and locales.
     */
    private function packageRoot(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    /** @return array<string,array<string,array{subject:string,body:string}>> */
    private function renderAll(array $templates, string $code, string $whoami, string $whereami): array
    {
        $out = [];
        foreach ($templates as $lang => $styles) {
            foreach ($styles as $style) {
                $out[$lang][$style] = [
                    'subject' => $this->renderSubject($lang, $style),
                    'body'    => $this->renderBody($lang, $style, $code, $whoami, $whereami),
                ];
            }
        }
        return $out;
    }

    private function renderBody(string $lang, string $style, string $code, string $whoami, string $whereami): string
    {
        push_lang($lang);
        $tx = replace_macros(get_intltext_template('invite.' . $style . '.tpl', $this->packageRoot()), [
            '$projectname'     => t('$Projectname'),
            '$invite_code'     => $code,
            '$invite_where'    => z_root() . '/register',
            '$invite_whereami' => $whereami,
            '$invite_whoami'   => z_root() . '/channel/' . $whoami,
            '$invite_anywhere' => z_root() . '/pubsites',
        ]);
        pop_lang();

        return $tx;
    }

    private function renderSubject(string $lang, string $style): string
    {
        if (!is_file($this->packageRoot() . 'view/lang/' . $lang . '/invite.' . $style . '.subject.tpl'))
            return t('Invitation');

        push_lang($lang);
        $ts = replace_macros(get_intltext_template('invite.' . $style . '.subject.tpl', $this->packageRoot()), [
            '$projectname' => t('$Projectname'),
            '$invite_loc'  => Config::Get('system', 'sitename'),
        ]);
        pop_lang();

        return trim($ts);
    }

    // ── Expiry ────────────────────────────────────────────────────────────────

    /**
     * Copied from core's private Invite::calcdue(). "2d" → a due timestamp.
     *
     * @return array{durn:string,durq:string,due:string}|false
     */
    public static function calcdue($duri = false)
    {
        if ($duri === false)
            $duri = Config::Get('system', 'register_expire', '2d');

        if (preg_match('/^[0-9]{1,2}[ihdwmy]{1}$/', $duri)) {
            $durq = substr($duri, -1);
            $durn = substr($duri, 0, -1);
            $due  = date('Y-m-d H:i:s', strtotime('+' . $durn . ' '
                . str_replace([':i', ':h', ':d', ':w', ':m', ':y'],
                    ['minutes', 'hours', 'days', 'weeks', 'months', 'years'],
                    (':' . $durq))
            ));
            return ['durn' => $durn, 'durq' => $durq, 'due' => $due];
        }

        return false;
    }
}
