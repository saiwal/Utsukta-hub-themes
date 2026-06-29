<?php
namespace Theme\Solidified\Api\Handlers;

use App;
use Zotlabs\Lib\Config;
use Theme\Solidified\Api\Response;

class Regate
{
    const REGISTER_AGREED = 0x0020;

    private function parseToken(string $token): array
    {
        if (strlen($token) < 2 || !ctype_xdigit(substr($token, 0, -1))) {
            Response::error(400, 'Invalid token format');
        }
        $type = substr($token, -1);
        $did2 = hex2bin(substr($token, 0, -1));
        return [$type, $did2];
    }

    private function lookupRecord(string $did2, string $type): array
    {
        $r = q(
            "SELECT * FROM register WHERE reg_vital = 1 AND reg_didx = '%s' AND reg_did2 = '%s' ORDER BY reg_created DESC LIMIT 1",
            dbesc($type), dbesc($did2)
        );
        if (!$r) {
            Response::error(404, 'Verification record not found or already used');
        }
        return $r[0];
    }

    public function get(): void
    {
        $token = \App::$argv[2] ?? '';
        if (!$token) {
            Response::error(400, 'Token is required');
        }

        [$type, $did2] = $this->parseToken($token);
        $reg = $this->lookupRecord($did2, $type);
        $now = datetime_convert();

        $expired = $reg['reg_expires'] < $now || $reg['reg_startup'] > $now;
        $pending_approval = !$expired && ($reg['reg_flags'] & ACCOUNT_PENDING) === ACCOUNT_PENDING
                          && !($reg['reg_flags'] & ACCOUNT_UNVERIFIED);

        Response::send([
            'type'             => $type,
            'email'            => $reg['reg_email'],
            'expired'          => $expired,
            'pending_approval' => $pending_approval,
        ]);
    }

    public function post(): void
    {
        $token = \App::$argv[2] ?? '';
        if (!$token) {
            Response::error(400, 'Token is required');
        }

        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($ct, 'application/json')) {
            Response::error(400, 'Content-Type must be application/json');
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $pin  = trim($body['pin'] ?? '');

        if (!$pin) {
            Response::error(400, 'Verification code is required');
        }

        [$type, $did2] = $this->parseToken($token);
        $reg = $this->lookupRecord($did2, $type);
        $now = datetime_convert();

        if ($reg['reg_expires'] < $now || $reg['reg_startup'] > $now) {
            Response::error(410, 'This verification link has expired');
        }

        // Validate pin format and compare
        $acpin = false;
        if ($type === 'e' && preg_match('/^[0-9a-f]{24}$/', $pin)) {
            $acpin = $pin;
        } elseif ($type === 'a' && preg_match('/^[0-9]{6}$/', $pin)) {
            $acpin = $pin;
        } elseif ($type === 'i') {
            $acpin = $reg['reg_hash'];
        }

        if ($acpin === false || $reg['reg_hash'] !== $acpin) {
            Response::error(400, 'Invalid verification code');
        }

        require_once('include/security.php');
        require_once('include/account.php');
        require_once('include/channel.php');

        $flags    = (int) $reg['reg_flags'];
        $reonar   = json_decode($reg['reg_stuff'], true) ?? [];
        $ip       = $_SERVER['REMOTE_ADDR'];

        $reonar['valid'] = $now . ',' . $ip;

        $newflags = ($flags & ~ACCOUNT_UNVERIFIED) | self::REGISTER_AGREED;
        $vital    = ($newflags & 0x1F) === 0 ? 0 : 1;

        q("START TRANSACTION");

        q(
            "UPDATE register SET reg_flags = %d, reg_vital = %d, reg_stuff = '%s' WHERE reg_id = %d",
            intval($newflags), intval($vital),
            dbesc(json_encode($reonar)),
            intval($reg['reg_id'])
        );

        if (($newflags & ACCOUNT_PENDING) === ACCOUNT_PENDING) {
            $approve = send_reg_approval_email_from_register((int) $reg['reg_id']);
            if ($approve['success']) {
                q("COMMIT");
            } else {
                q("ROLLBACK");
                Response::error(500, 'Failed to send approval notification');
            }
            Response::send(['next' => 'pending_approval']);
        }

        $cra = create_account_from_register(['reg_id' => (int) $reg['reg_id']]);

        if (!$cra['success']) {
            q("ROLLBACK");
            Response::error(500, $cra['message'] ?? 'Account creation failed');
        }

        q("COMMIT");

        $auto_create = (bool) Config::Get('system', 'auto_channel_create', 1);

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
