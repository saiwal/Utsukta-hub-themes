<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Cache;
use Zotlabs\Lib\Config;

class PasswordReset
{
    // Max reset requests per client IP within the window below.
    private const MAX_REQUESTS = 5;
    private const REQUEST_WINDOW = '15 MINUTE';
    private const TOKEN_TTL = 3600; // seconds
    // First half of account_reset is randomness; the rest is the expiry
    // packed as hex, so the whole token stays a single URL-safe hex string.
    private const RAND_HEX_LEN = 64;

    private function throttleKey(): string
    {
        return 'spa_pwreset_req:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    private function requestCount(): int
    {
        return (int) Cache::get($this->throttleKey(), self::REQUEST_WINDOW);
    }

    private function makeToken(): string
    {
        $rand = bin2hex(random_bytes(32));
        $expiresAt = time() + self::TOKEN_TTL;
        return $rand . dechex($expiresAt);
    }

    // Returns the matching account row, or null if the token is malformed,
    // unknown, or expired.
    private function lookupToken(string $token): ?array
    {
        if (strlen($token) <= self::RAND_HEX_LEN || !ctype_xdigit($token)) {
            return null;
        }

        $expiresAt = hexdec(substr($token, self::RAND_HEX_LEN));
        if ($expiresAt < time()) {
            return null;
        }

        $r = \q(
            "SELECT * FROM account WHERE account_reset = '%s' LIMIT 1",
            \dbesc($token)
        );

        return $r ? $r[0] : null;
    }

    public function get(): void
    {
        $token = \App::$argv[2] ?? '';

        if ($token) {
            $account = $this->lookupToken($token);
            Response::send(['valid' => (bool) $account]);
            return;
        }

        if (empty($_SESSION['solidified_pwreset_token'])) {
            $_SESSION['solidified_pwreset_token'] = bin2hex(random_bytes(32));
        }
        Response::send(['token' => $_SESSION['solidified_pwreset_token']]);
    }

    public function post(): void
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($ct, 'application/json')) {
            Response::error(400, 'Content-Type must be application/json');
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $urlToken = \App::$argv[2] ?? '';

        if ($urlToken) {
            $this->confirm($urlToken, $body);
            return;
        }

        $this->request($body);
    }

    // Step 1: request a reset email.
    private function request(array $body): void
    {
        $email = \notags(\punify(trim($body['email'] ?? '')));
        $token = $body['token'] ?? '';

        $expected = $_SESSION['solidified_pwreset_token'] ?? '';
        if (!$expected || !hash_equals($expected, $token)) {
            Response::error(403, 'Invalid security token');
        }
        unset($_SESSION['solidified_pwreset_token']);

        if (!$email) {
            Response::error(400, 'Email address is required');
        }

        if ($this->requestCount() >= self::MAX_REQUESTS) {
            Response::error(429, 'Too many reset requests. Please try again later.');
        }
        Cache::set($this->throttleKey(), (string) ($this->requestCount() + 1));

        $r = \q(
            "SELECT * FROM account WHERE account_email = '%s' LIMIT 1",
            \dbesc($email)
        );

        // Same response whether or not the account exists — no enumeration.
        if ($r) {
            $account = $r[0];
            $resetToken = $this->makeToken();

            \q(
                "UPDATE account SET account_reset = '%s' WHERE account_id = %d",
                \dbesc($resetToken),
                intval($account['account_id'])
            );

            $siteName = Config::Get('system', 'sitename');
            $resetLink = \z_root() . '/reset-password/' . $resetToken;

            $message = "A request was recently received to reset your account password at {$siteName}.\n\n"
                . "If you made this request, follow this link to choose a new password:\n\n"
                . "{$resetLink}\n\n"
                . "This link will expire in one hour. If you did not request this change, "
                . "you can safely ignore this email — your password will not be changed.\n";

            \z_mail([
                'toEmail' => $email,
                'messageSubject' => \email_header_encode("Password reset requested at {$siteName}", 'UTF-8'),
                'textVersion' => $message,
            ]);
        }

        Response::send(['sent' => true]);
    }

    // Step 2: confirm the reset with a new password.
    private function confirm(string $urlToken, array $body): void
    {
        $account = $this->lookupToken($urlToken);
        if (!$account) {
            Response::error(410, 'This reset link is invalid or has expired');
        }

        $password = $body['password'] ?? '';
        $password2 = $body['password2'] ?? '';

        if (!$password) {
            Response::error(400, 'Password is required');
        }
        if ($password !== $password2) {
            Response::error(400, 'Passwords do not match');
        }

        require_once('include/account.php');
        $pwCheck = \check_account_password($password);
        if (!empty($pwCheck['error'])) {
            Response::error(400, $pwCheck['message'] ?? 'Password does not meet requirements');
        }

        $salt = \random_string(32);
        $passwordEncoded = hash('whirlpool', $salt . $password);

        \q(
            "UPDATE account SET account_salt = '%s', account_password = '%s', account_reset = '', account_flags = (account_flags & ~%d) WHERE account_id = %d",
            \dbesc($salt),
            \dbesc($passwordEncoded),
            intval(ACCOUNT_UNVERIFIED),
            intval($account['account_id'])
        );

        $siteName = Config::Get('system', 'sitename');
        $message = "Your password at {$siteName} has just been changed.\n\n"
            . "If you did not make this change, please contact the site administrator immediately.\n";

        \z_mail([
            'toEmail' => $account['account_email'],
            'messageSubject' => \email_header_encode("Your password has changed at {$siteName}", 'UTF-8'),
            'textVersion' => $message,
        ]);

        Response::send(['success' => true]);
    }
}
