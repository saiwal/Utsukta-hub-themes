<?php
namespace Theme\Solidified\Api\Handlers;

use App;
use Zotlabs\Lib\Config;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Register
{
    // Defined in Regate.php at file scope; redeclare here to avoid dependency.
    const REGISTER_AGREED = 0x0020;

    public function get(): void
    {
        $policy = (int) Config::Get('system', 'register_policy');

        if ($policy === REGISTER_CLOSED) {
            Response::send(['policy' => $policy, 'closed' => true]);
            return;
        }

        $registerToken = Auth::issueFormToken('spa_register_tok');

        require_once('include/bbcode.php');
        $register_text_raw = Config::Get('system', 'register_text') ?? '';
        $register_text = $register_text_raw ? bbcode($register_text_raw) : '';

        $tos_url = Config::Get('system', 'tos_url') ?: (z_root() . '/help/TermsOfService');

        Response::send([
            'policy'              => $policy,
            'closed'              => false,
            'invite_only'         => (bool) Config::Get('system', 'invitation_only'),
            'invite_also'         => (bool) Config::Get('system', 'invitation_also'),
            'auto_channel_create' => (bool) Config::Get('system', 'auto_channel_create', 1),
            'verify_email'        => (bool) Config::Get('system', 'verify_email'),
            'tos_url'             => $tos_url,
            'min_age'             => (int) (Config::Get('system', 'minimum_age') ?: 13),
            'no_age_restriction'  => (bool) Config::Get('system', 'no_age_restriction'),
            'enable_tos'          => !((bool) Config::Get('system', 'no_termsofservice')),
            'register_text'       => $register_text,
            'site_name'           => \Zotlabs\Lib\System::get_site_name(),
            'nickhub'             => '@' . str_replace(['http://', 'https://', '/'], '', Config::Get('system', 'baseurl')),
            'token'               => $registerToken,
        ]);
    }

    public function post(): void
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($ct, 'application/json')) {
            Response::error(400, 'Content-Type must be application/json');
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $token = $body['token'] ?? '';
        if (!Auth::validateFormToken('spa_register_tok', $token)) {
            Response::error(403, 'Invalid security token');
        }

        require_once('include/security.php');
        require_once('include/account.php');
        require_once('include/channel.php');

        $policy = (int) Config::Get('system', 'register_policy');

        if ($policy === REGISTER_CLOSED && !is_site_admin()) {
            Response::error(403, 'Registration is closed on this hub');
        }

        $auto_create  = (bool) Config::Get('system', 'auto_channel_create', 1);
        $email_verify = (bool) Config::Get('system', 'verify_email');
        $invite_only  = (bool) Config::Get('system', 'invitation_only');
        $invite_also  = (bool) Config::Get('system', 'invitation_also');

        $email        = isset($body['email'])    ? notags(punify(trim($body['email'])))        : '';
        $password     = isset($body['password']) ? trim($body['password'])                      : '';
        $password2    = isset($body['password2'])? trim($body['password2'])                     : '';
        $name         = $auto_create ? escape_tags(trim($body['name'] ?? ''))                   : '';
        $nickname     = $auto_create ? mb_strtolower(escape_tags(trim($body['nickname'] ?? ''))) : '';
        $tos          = !empty($body['tos']);
        $invite_code  = notags(trim($body['invite_code'] ?? ''));
        $register_msg = notags(trim($body['register_msg'] ?? ''));

        if (!$password) {
            Response::error(400, 'Password is required');
        }
        if ($password !== $password2) {
            Response::error(400, 'Passwords do not match');
        }
        $pw_check = check_account_password($password);
        if (!empty($pw_check['error'])) {
            Response::error(400, $pw_check['message'] ?? 'Password does not meet requirements');
        }

        if ($email_verify && !$email) {
            Response::error(400, 'Email address is required');
        }
        if ($email) {
            $email_result = check_account_email($email);
            if ($email_result['error']) {
                Response::error(400, $email_result['message']);
            }
        }

        if ($invite_only && !$invite_code) {
            Response::error(400, 'An invitation code is required to register');
        }
        if ($invite_code && ($invite_only || $invite_also)) {
            $invite_check = check_account_invite($invite_code);
            if ($invite_check['error']) {
                Response::error(400, $invite_check['message']);
            }
        }

        if ($auto_create) {
            if (!$name) {
                Response::error(400, 'Name is required');
            }
            $name_error = validate_channelname($name);
            if ($name_error) {
                Response::error(400, $name_error);
            }
            if (!$nickname) {
                Response::error(400, 'Nickname is required');
            }
            if ($nickname === 'sys') {
                Response::error(400, 'Reserved nickname. Please choose another.');
            }
            if (check_webbie([$nickname]) !== $nickname) {
                Response::error(400, 'Nickname is already in use or contains unsupported characters');
            }
        }

        if (!$tos) {
            Response::error(400, 'You must accept the Terms of Service');
        }

        switch ($policy) {
            case REGISTER_OPEN:    $flags = ACCOUNT_OK;      break;
            case REGISTER_APPROVE: $flags = ACCOUNT_PENDING; break;
            default:               $flags = ACCOUNT_OK;      break;
        }

        $salt             = random_string(32);
        $password_encoded = $salt . ',' . hash('whirlpool', $salt . $password);

        $now       = datetime_convert();
        $ip        = $_SERVER['REMOTE_ADDR'];
        $reonar    = [];

        if ($auto_create) {
            $reonar['chan.name'] = $name;
            $reonar['chan.did1'] = $nickname;
        }
        if ($policy === REGISTER_APPROVE) {
            $reonar['msg'] = $register_msg;
        }

        $cfgexpire  = Config::Get('system', 'register_expire', '3d');
        $reg_expires = function_exists('calculate_adue') ? calculate_adue($cfgexpire) : null;
        $regexpire  = $reg_expires
            ? datetime_convert(date_default_timezone_get(), 'UTC', $reg_expires['due'])
            : datetime_convert('UTC', 'UTC', 'now + 3 days');

        // EMAIL VERIFICATION path
        if ($email_verify && $email) {
            $flags |= ACCOUNT_UNVERIFIED;
            $empin  = random_string(24);

            $reonar['from']        = Config::Get('system', 'from_email');
            $reonar['to']          = $email;
            $reonar['subject']     = sprintf(t('Registration confirmation for %s'), Config::Get('system', 'sitename'));
            $reonar['txttemplate'] = replace_macros(
                get_intltext_template('register_verify_member.tpl'),
                [
                    '$sitename' => Config::Get('system', 'sitename'),
                    '$siteurl'  => z_root(),
                    '$email'    => $email,
                    '$mail'     => bin2hex($email) . 'e',
                    '$hash'     => $empin,
                    '$ko'       => bin2hex(substr($empin, 0, 4)),
                ]
            );

            q(
                "INSERT INTO register (reg_flags,reg_didx,reg_did2,reg_hash,reg_created,reg_startup,reg_expires,reg_email,reg_pass,reg_lang,reg_atip,reg_stuff)"
                . " VALUES (%d,'e','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",
                intval($flags),
                dbesc($email), dbesc($empin),
                dbesc($now), dbesc($now), dbesc($regexpire),
                dbesc($email), dbesc($password_encoded),
                dbesc(App::$language), dbesc($ip),
                dbesc(json_encode($reonar))
            );

            zar_reg_mail($reonar);

            $regate_url = z_root() . '/regate/' . bin2hex($email) . 'e';
            Response::send(['next' => 'check_email', 'regate_url' => $regate_url]);
        }

        // DIRECT path (no email verify) — perform register + regate inline
        $flags |= ACCOUNT_UNVERIFIED;
        $acpin = (string) rand(100000, 999999);
        $did2  = (string) rand(10, 99);

        if ($email) {
            $reonar['email.untrust']  = $email;
            $reonar['email.comment']  = 'provided but not verified';
        }

        q(
            "INSERT INTO register (reg_flags,reg_didx,reg_did2,reg_hash,reg_created,reg_startup,reg_expires,reg_email,reg_pass,reg_lang,reg_atip,reg_stuff)"
            . " VALUES (%d,'a','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",
            intval($flags),
            dbesc($did2), dbesc($acpin),
            dbesc($now), dbesc($now), dbesc($regexpire),
            dbesc($email), dbesc($password_encoded),
            dbesc(App::$language), dbesc($ip),
            dbesc(json_encode($reonar))
        );

        $lid = q(
            "SELECT reg_id FROM register WHERE reg_vital = 1 AND reg_did2 = '%s' AND reg_pass = '%s'",
            dbesc($did2), dbesc($password_encoded)
        );

        if (!$lid || count($lid) !== 1) {
            Response::error(500, 'Registration failed. Please try again.');
        }

        $reg_id = (int) $lid[0]['reg_id'];
        $didnew = ($reg_id . $did2) . substr(base_convert(md5($reg_id . $did2), 16, 10), -2);

        q("UPDATE register SET reg_did2 = CONCAT('d','%s') WHERE reg_id = %d",
            dbesc($didnew), intval($reg_id));

        // Inline regate: clear ACCOUNT_UNVERIFIED, set REGISTER_AGREED
        $newflags = ($flags & ~ACCOUNT_UNVERIFIED) | self::REGISTER_AGREED;
        $vital    = ($newflags & 0x1F) === 0 ? 0 : 1;

        q("UPDATE register SET reg_flags = %d, reg_vital = %d WHERE reg_id = %d",
            intval($newflags), intval($vital), intval($reg_id));

        if ($policy === REGISTER_APPROVE) {
            send_reg_approval_email_from_register($reg_id);
            Response::send(['next' => 'pending_approval']);
        }

        $cra = create_account_from_register(['reg_id' => $reg_id]);
        if (!$cra['success']) {
            Response::error(500, $cra['message'] ?? 'Account creation failed');
        }

        if ($auto_create && !empty($reonar['chan.did1'])) {
            if (!empty($reonar['chan.name'])) {
                set_aconfig($cra['account']['account_id'], 'register', 'channel_name', $reonar['chan.name']);
            }
            set_aconfig($cra['account']['account_id'], 'register', 'channel_address', $reonar['chan.did1']);

            $permissions_role = Config::Get('system', 'default_permissions_role');
            if ($permissions_role) {
                set_aconfig($cra['account']['account_id'], 'register', 'permissions_role', $permissions_role);
            }

            auto_channel_create($cra['account']['account_id']);
        }

        authenticate_success($cra['account'], null, true, false, true);
        $ch = App::get_channel();

        Response::send([
            'next' => 'complete',
            'nick' => $ch['channel_address'] ?? '',
        ]);
    }
}
