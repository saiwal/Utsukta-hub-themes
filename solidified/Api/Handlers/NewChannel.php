<?php
namespace Theme\Solidified\Api\Handlers;

use URLify;
use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
use Zotlabs\Access\PermissionRoles;
use Theme\Solidified\Api\Response;

class NewChannel
{
    // ── GET /api/new-channel[/autofill|/checkaddr] ──────────────────────────────

    public function get(): void
    {
        $account = $this->requireAccount();

        $sub = App::$argv[2] ?? '';
        if ($sub === 'autofill') {
            $this->autofill();
            return;
        }
        if ($sub === 'checkaddr') {
            $this->checkaddr();
            return;
        }

        $this->meta($account);
    }

    // ── POST /api/new-channel ────────────────────────────────────────────────────

    public function post(): void
    {
        $account = $this->requireAccount();
        Csrf::validate();

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            Response::error(400, 'Invalid JSON body');
        }

        require_once('include/channel.php');
        require_once('include/permissions.php');

        $name     = escape_tags(trim($body['name'] ?? ''));
        $nickname = mb_strtolower(trim($body['nickname'] ?? ''));
        $role     = notags(trim($body['permissions_role'] ?? ''));

        if (!$name) {
            Response::error(400, 'Channel name is required');
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

        $valid_roles = array_keys(PermissionRoles::channel_roles());
        if ($role && !in_array($role, $valid_roles, true)) {
            Response::error(400, 'Invalid channel role');
        }

        $arr = [
            'account_id' => intval($account['account_id']),
            'name'       => $name,
            'nickname'   => $nickname,
        ];
        if ($role) {
            $arr['permissions_role'] = $role;
        }

        $result = create_identity($arr);
        if (!$result['success']) {
            Response::error(400, $result['message'] ?? 'Channel creation failed');
        }

        $newuid = intval($result['channel']['channel_id']);
        change_channel($newuid);

        $this->installProtocols($newuid, $body['protocols'] ?? []);
        $this->applyDisplaySettings($newuid, $body);

        Response::send([
            'channel_id'  => $newuid,
            'nick'        => $result['channel']['channel_address'],
            'redirect_to' => '/hq',
        ]);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    // Channel creation happens at the account level — the account may not have
    // a channel selected yet (e.g. right after registration), so local_channel()
    // based auth (Auth::requireLocal*) doesn't apply here.
    private function requireAccount(): array
    {
        $account = App::get_account();
        if (!$account || intval($account['account_id'] ?? 0) !== intval(get_account_id())) {
            Response::error(403, 'Permission denied');
        }
        return $account;
    }

    // ── GET builders ──────────────────────────────────────────────────────────

    private function meta(array $account): void
    {
        $aid = intval($account['account_id']);

        $r = q(
            "SELECT COUNT(channel_id) AS total FROM channel WHERE channel_account_id = %d AND channel_removed = 0",
            $aid
        );
        $total = intval($r[0]['total'] ?? 0);

        $default_role = $total === 0 ? Config::Get('system', 'default_permissions_role', 'personal') : '';

        $limit = account_service_class_fetch($aid, 'total_identities');
        $canadd = $limit === false || $total <= intval($limit);

        $roles = [];
        foreach (PermissionRoles::channel_roles() as $value => $label) {
            $roles[] = ['value' => $value, 'label' => $label];
        }

        Response::send([
            'default_role'   => $default_role,
            'roles'          => $roles,
            'nickhub'        => '@' . str_replace(['http://', 'https://', '/'], '', Config::Get('system', 'baseurl')),
            'total_channels' => $total,
            'limit'          => $limit !== false ? intval($limit) : null,
            'canadd'         => $canadd,
            'protocols'      => $this->federationApps(),
        ]);
    }

    private function autofill(): void
    {
        Response::send(['suggestion' => $this->suggestWebbie(trim($_GET['name'] ?? ''), false)]);
    }

    private function checkaddr(): void
    {
        $nick = trim($_GET['nickname'] ?? '');
        $name = trim($_GET['name'] ?? '');
        $n = $nick ?: $name;

        $desired = legal_webbie($n);
        $suggestion = $this->suggestWebbie($n, true);

        Response::send([
            'suggestion' => $suggestion,
            'available'  => $desired !== '' && $suggestion === $desired,
        ]);
    }

    // Mirrors core's autofill.json / checkaddr.json candidate-generation logic
    // (Zotlabs\Module\New_channel::init()) so suggestions match what core would offer.
    private function suggestWebbie(string $n, bool $withFallbacks): string
    {
        $x = false;
        if (Config::Get('system', 'unicode_usernames')) {
            $x = punify(mb_strtolower($n));
        }
        if (!$x || strlen($x) > 64) {
            $x = strtolower(URLify::transliterate($n));
        }

        $test = [];
        if (strpos($x, ' ')) {
            $test[] = legal_webbie(substr($x, 0, strpos($x, ' ')));
        }
        if (!empty($test[0])) {
            $test[] = strpos($x, ' ') ? $test[0] . legal_webbie(trim(substr($x, strpos($x, ' '), 2))) : '';
            $test[] = $test[0] . mt_rand(1000, 9999);
        }
        $test[] = legal_webbie($x);
        $test[] = legal_webbie($x) . mt_rand(1000, 9999);

        if ($withFallbacks) {
            for ($y = 0; $y < 100; $y++) {
                $test[] = 'id' . mt_rand(1000, 9999);
            }
        }

        return check_webbie($test);
    }

    // Federation-category apps (Diaspora Protocol, Activitypub Protocol, ...) are only
    // present here when the site admin has enabled the underlying addon — the first
    // half of the double gate. Installing one below (postIntegrationsSettings-style)
    // is the second half, scoped to the new channel.
    //
    // $sync=true is required: both diaspora.apd and pubcrawl.apd declare
    // "requires: local_channel", and parse_app_description() drops any app
    // failing its `requires` line when that flag is false — which local_channel()
    // always is here, since we're still in the middle of creating the channel.
    // $sync=true skips that gating. Safe because we still scope to the
    // "Federation" category below, so no admin-only/other gated apps leak in.
    private function federationApps(): array
    {
        $system = Apps::get_system_apps(true, true);
        $protocols = [];
        foreach ($system as $app) {
            $categories = $app['categories'] ?? '';
            if (!str_contains($categories, 'Federation')) continue;
            $protocols[] = [
                'name'        => $app['name'] ?? '',
                'description' => $app['desc'] ?? '',
                'photo'       => $app['photo'] ?? '',
            ];
        }
        return $protocols;
    }

    // ── POST helpers ──────────────────────────────────────────────────────────

    private function installProtocols(int $uid, mixed $protocols): void
    {
        if (!is_array($protocols) || !$protocols) return;

        // $sync=true — see federationApps() for why (requires: local_channel gating).
        $system = Apps::get_system_apps(true, true);
        foreach ($system as $app) {
            $categories = $app['categories'] ?? '';
            if (!str_contains($categories, 'Federation')) continue;
            if (!in_array($app['name'] ?? '', $protocols, true)) continue;

            $app['uid']    = $uid;
            $app['system'] = 1;
            Apps::app_install($uid, $app);
        }
    }

    private function applyDisplaySettings(int $uid, array $body): void
    {
        $valid_color_schemes = [
            'light', 'pastel-soft', 'warm-paper', 'mint', 'sakura', 'latte-cream',
            'dark', 'nord', 'dracula', 'monokai', 'one-dark', 'cyberpunk',
            'rose-pine', 'gruvbox-dark', 'gruvbox-light', 'catppuccin-latte',
            'catppuccin-mocha', 'solarized-light', 'solarized-dark', 'tokyo-night', 'matrix',
            'high-contrast', 'high-contrast-light', 'custom',
        ];
        if (isset($body['color_scheme']) && in_array($body['color_scheme'], $valid_color_schemes, true)) {
            set_pconfig($uid, 'spa', 'color_scheme', $body['color_scheme']);
        }

        if (isset($body['custom_theme_colors'])) {
            $raw = (string) $body['custom_theme_colors'];
            $decoded = json_decode($raw, true);
            if (
                is_array($decoded)
                && isset($decoded['base'], $decoded['txt'], $decoded['accent'], $decoded['isDark'])
                && preg_match('/^#[0-9a-fA-F]{6}$/', $decoded['base'])
                && preg_match('/^#[0-9a-fA-F]{6}$/', $decoded['txt'])
                && preg_match('/^#[0-9a-fA-F]{6}$/', $decoded['accent'])
                && is_bool($decoded['isDark'])
            ) {
                set_pconfig($uid, 'spa', 'custom_theme_colors', $raw);
            }
        }

        $valid_font_sizes = ['small', 'medium', 'large', 'xl'];
        if (isset($body['font_size']) && in_array($body['font_size'], $valid_font_sizes, true)) {
            set_pconfig($uid, 'spa', 'font_size', $body['font_size']);
        }

        $valid_corner_radii = ['none', 'sm', 'default', 'lg', 'xl'];
        if (isset($body['corner_radius']) && in_array($body['corner_radius'], $valid_corner_radii, true)) {
            set_pconfig($uid, 'spa', 'corner_radius', $body['corner_radius']);
        }
    }
}
