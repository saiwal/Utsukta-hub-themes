<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

class Login
{
    public function get(): void
    {
        if (empty($_SESSION['solidified_login_token'])) {
            $_SESSION['solidified_login_token'] = bin2hex(random_bytes(32));
        }
        Response::send(['token' => $_SESSION['solidified_login_token']]);
    }

    public function post(): void
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($ct, 'application/json')) {
            Response::error(400, 'Content-Type must be application/json');
        }

        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        $token    = $body['token']    ?? '';

        if (!$username || !$password) {
            Response::error(400, 'Email and password are required');
        }

        $expected = $_SESSION['solidified_login_token'] ?? '';
        if (!$expected || !hash_equals($expected, $token)) {
            Response::error(403, 'Invalid security token');
        }
        unset($_SESSION['solidified_login_token']);

        // security.php is only loaded via auth.php which is conditionally included.
        // Require it directly — it contains only function definitions (no inline code).
        require_once('include/security.php');

        // Look up account by email or channel nick, verify whirlpool(salt.pass) hash
        $account = null;
        $channel = null;

        if (str_contains($username, '@')) {
            $r = \q("SELECT * FROM account WHERE account_email = '%s' LIMIT 1", \dbesc($username));
            if ($r) $account = $r[0];
        } else {
            $c = \q("SELECT * FROM channel WHERE channel_address = '%s' AND channel_removed = 0 LIMIT 1",
                \dbesc($username));
            if ($c) {
                $channel = $c[0];
                $r = \q("SELECT * FROM account WHERE account_id = %d LIMIT 1",
                    intval($c[0]['channel_account_id']));
                if ($r) $account = $r[0];
            }
        }

        if (!$account) {
            Response::error(401, 'Invalid username or password');
        }

        // Hubzilla stores passwords as hash('whirlpool', salt . password)
        $hash = \hash('whirlpool', $account['account_salt'] . $password);
        if (!\hash_equals($account['account_password'], $hash)) {
            Response::error(401, 'Invalid username or password');
        }

        if ($account['account_flags'] !== 0) {
            Response::error(403, 'Account is not active');
        }

        // $return=true prevents authenticate_success from calling goaway()
        \authenticate_success($account, $channel, true, true, true);

        $ch = \App::get_channel();
        Response::send(['nick' => $ch['channel_address'] ?? '']);
    }
}
