<?php
// Api/Concerns/ValidatesRemoteHost.php
namespace Theme\Solidified\Api\Concerns;

/**
 * Shared SSRF guard for handlers that fetch a server-supplied or
 * client-influenced remote URL (Rss.php's feed fetch, Portability.php's
 * hub-migration probe). Resolves a hostname to a single IP and rejects
 * private/loopback/link-local/reserved ranges — including IPv4-mapped or
 * IPv4-compatible IPv6 literals that wrap a private IPv4 address. Fails
 * closed: any resolution failure returns false rather than "allowed".
 */
trait ValidatesRemoteHost
{
    protected static function resolveSafePublicIp(string $host): string|false
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::ipIsPublic($host) ? $host : false;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (!$records) {
            return false;
        }

        foreach ($records as $rec) {
            $ip = ($rec['type'] ?? '') === 'AAAA' ? ($rec['ipv6'] ?? '') : ($rec['ip'] ?? '');
            if ($ip && self::ipIsPublic($ip)) {
                return $ip;
            }
        }

        return false;
    }

    protected static function ipIsPublic(string $ip): bool
    {
        // Unwrap IPv4-mapped/compat IPv6 (::ffff:10.0.0.1, ::10.0.0.1) and
        // validate the embedded IPv4 too — the outer-form check alone
        // doesn't catch a private address hiding inside a "public-looking"
        // IPv6 literal.
        if (str_contains($ip, ':') && preg_match('/(\d+\.\d+\.\d+\.\d+)$/', $ip, $m)) {
            if (!filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
