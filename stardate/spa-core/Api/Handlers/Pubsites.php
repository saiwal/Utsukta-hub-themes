<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Concerns\ValidatesRemoteHost;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\Libzotdir;

class Pubsites
{
    use ValidatesRemoteHost;

    public function get(): void
    {
        if (argv(2) === 'info') {
            $this->info();
            return;
        }

        $dirmode = intval(Config::Get('system', 'directory_mode'));
        $url = '';

        if ($dirmode == DIRECTORY_MODE_PRIMARY || $dirmode == DIRECTORY_MODE_STANDALONE) {
            $url = z_root() . '/dirsearch';
        }

        if (!$url) {
            $directory = Libzotdir::find_upstream_directory($dirmode);
            $url = $directory['url'] . '/dirsearch';
        }

        $url .= '/sites';

        $ret = z_fetch_url($url);

        if (!$ret['success']) {
            Response::send(['error' => 'Failed to fetch sites'], 500);
        }

        $j = json_decode($ret['body'], true);
        $sites = [];

        if ($j && !empty($j['sites'])) {
            foreach ($j['sites'] as $jj) {
                $projectname = explode(' ', $jj['project']);
                if (!\Zotlabs\Lib\System::compatible_project($projectname[0]))
                    continue;

                if (strpos($jj['version'], ' ')) {
                    $x = explode(' ', $jj['version']);
                    if ($x[1])
                        $jj['version'] = $x[1];
                }

                $sites[] = [
                    'urltext' => str_replace(['https://', 'http://'], '', $jj['url']),
                    'host' => str_replace(['https://', 'http://'], '', $jj['url']),
                    'sellpage' => !empty($jj['sellpage']) ? $jj['sellpage'] : null,
                    'register_url' => !empty($jj['sellpage']) ? $jj['sellpage'] : rtrim($jj['url'], '/') . '/register',
                    'url' => $jj['url'],
                    'access' => $jj['access'] ?? '',
                    'register' => $jj['register'] ?? '',
                    'project' => ucwords($jj['project']),
                    'version' => $jj['version'] ?? '',
                    'location' => $jj['location'] ?? '',
                    'rating_enabled' => (bool) Config::Get('system', 'rating_enabled'),
                    'can_rate' => (bool) local_channel(),
                ];
            }
        }

        Response::send(['sites' => $sites]);
    }

    /** Largest siteinfo.json accepted; a real one is ~2 KB. */
    private const INFO_MAX_BYTES = 262144;

    /**
     * GET /spa/pubsites/info?host=<host> — proxies another hub's public
     * /siteinfo.json, which core serves without CORS headers so the browser
     * can't read it directly. The host is client-supplied and this endpoint
     * is public, so: fixed https path, public IP only, curl pinned to the
     * validated IP (no DNS-rebind gap), redirects not followed, body capped,
     * and every result — failures too — cached an hour per host so the hub
     * can't be used to hammer a third party. The remote JSON is untrusted:
     * only known fields are passed on, each coerced to its type and capped.
     */
    private function info(): void
    {
        $host = strtolower(trim((string)($_GET['host'] ?? '')));
        $parts = parse_url('https://' . $host);
        $name = $parts['host'] ?? '';
        if (!$name || strlen($host) > 253 || !preg_match('/^[a-z0-9.-]+$/', $name)
            || ($parts['path'] ?? '') !== '' || isset($parts['user']) || isset($parts['query'])
            || isset($parts['fragment']) || $name === 'localhost') {
            Response::error(400, 'Invalid host');
        }
        $port = intval($parts['port'] ?? 443);
        $key = "spa_hubinfo:$name:$port";

        $cached = \Zotlabs\Lib\Cache::get($key, '1 HOUR');
        if ($cached !== null) {
            $data = json_decode($cached, true);
        } else {
            $data = self::fetchInfo($name, $port);
            \Zotlabs\Lib\Cache::set($key, json_encode($data));
        }
        if (!is_array($data)) {
            Response::error(502, 'Hub did not return site info');
        }
        Response::send($data);
    }

    private static function fetchInfo(string $name, int $port): ?array
    {
        $ip = self::resolveSafePublicIp($name);
        if (!$ip) {
            return null;
        }

        $body = '';
        $ch = curl_init("https://$name:$port/siteinfo.json");
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => ["$name:$port:$ip"],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => \Zotlabs\Lib\System::get_useragent(),
            // Returning a short count aborts the transfer — the cap holds
            // even for a chunked response with no Content-Length.
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body) {
                $body .= $chunk;
                return strlen($body) > self::INFO_MAX_BYTES ? 0 : strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        $j = ($ok && $code === 200) ? json_decode($body, true, 8) : null;
        return is_array($j) ? self::sanitizeInfo($j) : null;
    }

    private static function sanitizeInfo(array $j): array
    {
        $str = fn($v, int $max = 200) => is_scalar($v) ? mb_substr(trim(strip_tags((string)$v)), 0, $max) : '';
        $int = fn($v) => is_numeric($v) ? max(0, intval($v)) : null;
        $url = fn($v) => (is_string($v) && preg_match('#^https?://#i', $v) && filter_var($v, FILTER_VALIDATE_URL))
            ? mb_substr($v, 0, 500) : '';
        $list = fn($v, int $max = 200) => is_array($v)
            ? array_values(array_filter(array_map($str, array_slice(array_values($v), 0, $max))))
            : [];

        $admins = [];
        foreach (array_slice(is_array($j['admin'] ?? null) ? $j['admin'] : [], 0, 20) as $a) {
            if (is_array($a)) {
                $admins[] = ['name' => $str($a['name'] ?? ''), 'address' => $str($a['address'] ?? ''), 'channel' => $url($a['channel'] ?? '')];
            }
        }

        $limits = [];
        foreach (array_slice(is_array($j['default_service_restrictions'] ?? null) ? $j['default_service_restrictions'] : [], 0, 50, true) as $k => $v) {
            if (preg_match('/^[a-z_]{1,64}$/', (string)$k) && is_numeric($v)) {
                $limits[$k] = intval($v);
            }
        }

        $locked = [];
        foreach (array_slice(is_array($j['locked_features'] ?? null) ? $j['locked_features'] : [], 0, 100, true) as $k => $v) {
            if (preg_match('/^[a-z0-9_]{1,64}$/', (string)$k)) {
                $locked[] = $k;
            }
        }

        $out = [
            'url' => $url($j['url'] ?? ''),
            'platform' => $str($j['platform'] ?? '', 50),
            'site_name' => $str($j['site_name'] ?? ''),
            'version' => $str($j['version'] ?? '', 50),
            'version_tag' => $str($j['version_tag'] ?? '', 50),
            'server_role' => $str($j['server_role'] ?? '', 50),
            'commit' => $str($j['commit'] ?? '', 16),
            'plugins' => $list($j['plugins'] ?? []),
            'register_policy' => $str($j['register_policy'] ?? '', 50),
            'invitation_only' => !empty($j['invitation_only']),
            'directory_mode' => $str($j['directory_mode'] ?? '', 50),
            'directory_server' => $url($j['directory_server'] ?? ''),
            'language' => $str($j['language'] ?? '', 20),
            'rss_connections' => !empty($j['rss_connections']),
            'expiration' => $int($j['expiration'] ?? null),
            'default_service_restrictions' => $limits,
            'locked_features' => $locked,
            'admin' => $admins,
            'dbdriver' => $str($j['dbdriver'] ?? '', 50),
            'lastpoll' => $str($j['lastpoll'] ?? '', 30),
            'info' => $str($j['info'] ?? '', 2000),
            'channels_total' => $int($j['channels_total'] ?? null),
            'hide_in_statistics' => !empty($j['hide_in_statistics']),
        ];
        foreach (['channels_active_halfyear', 'channels_active_monthly', 'local_posts', 'local_comments'] as $k) {
            $out[$k] = $out['hide_in_statistics'] ? null : $int($j[$k] ?? null);
        }
        return $out;
    }
}
