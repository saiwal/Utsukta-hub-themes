<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;

/**
 * A per-channel vault for third-party credentials (Jellyfin now; PeerTube,
 * Matrix… later), so a sign-in made in one browser can reach the user's other
 * browsers and the channel's clones.
 *
 * Entries arrive already end-to-end encrypted by the browser (spa-core
 * lib/vault.ts: a key derived from the user's vault passphrase, AES-GCM), so
 * this handler — and the hub's admin — never see what they hold. The channel
 * key seal below is a second layer that only keeps even the ciphertext out of
 * logs and dumps.
 *
 *   GET  /spa/vault/<integration>                    → { data: { <key>: <entry>, … } }
 *   POST /spa/vault/<integration>  { key, entry }     → store/replace one entry
 *   POST /spa/vault/<integration>  { key, delete: 1 } → remove one entry
 *   POST /spa/vault/<integration>  { clear: 1 }       → drop every entry (works on a vault that no longer opens)
 *
 * A stored value that can't be opened (rotated channel key, corrupt row) is a
 * 409, never "empty": treating it as empty let the client set a new
 * passphrase over the old vault.
 *
 * Owner only. Entries are opaque JSON objects to this handler.
 *
 * Storage: pconfig cat "spa_vault", one row per entry, key
 * "<integration>:<entry key>", each sealed with the channel's own key pair
 * (core's crypto_encapsulate: a random AES-256 key wrapped by channel_pubkey).
 * Clones share the channel's keys, so every clone can open it; the router's
 * shutdown hook syncs the pconfig writes to them like any other, and core
 * syncs a del_pconfig by itself. Sealed because core logs whole sync packets
 * at LOGGER_DATA, and pconfig rides backups and table dumps.
 *
 * One row per entry is load-bearing: clone sync is last-writer-wins per
 * pconfig row, so with the old one-map-per-integration row two clones each
 * saving a different entry silently lost one. A legacy map row (key =
 * "<integration>", no colon) is split up the first time it is read.
 */
class Vault
{
    private const CAT = 'spa_vault';
    private const NAME = '/^[a-z0-9_-]{1,32}$/';
    // Lowercase only: pconfig.k compares case-insensitively on MySQL
    private const KEY = '/^[a-z0-9._:-]{1,128}$/';
    private const MAX_ENTRIES = 32;
    private const MAX_ENTRY_BYTES = 8192; // client ciphertext + base64

    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $name = self::integration();
        header('Cache-Control: no-store');
        Response::send((object) self::openOrFail($uid, $name));
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $name = self::integration();
        $body = Auth::$parsedBody;

        if (!empty($body['clear'])) {
            self::clear($uid, $name);
            Response::send(['stored' => true]);
        }

        $key = (string) ($body['key'] ?? '');
        if (!preg_match(self::KEY, $key)) Response::error(400, 'Invalid key');

        if (!empty($body['delete'])) {
            self::migrate($uid, $name);
            del_pconfig($uid, self::CAT, "$name:$key");
        } else {
            $entry = $body['entry'] ?? null;
            if (!is_array($entry) || array_is_list($entry)) Response::error(400, 'Entry must be an object');
            if (strlen(json_encode($entry)) > self::MAX_ENTRY_BYTES) Response::error(413, 'Entry too large');
            $map = self::openOrFail($uid, $name);
            if (!isset($map[$key]) && count($map) >= self::MAX_ENTRIES) Response::error(413, 'Too many entries');
            self::put($uid, $name, $key, $entry);
        }
        Response::send(['stored' => true]);
    }

    private static function integration(): string
    {
        $name = (string) (\App::$argv[2] ?? '');
        if (!preg_match(self::NAME, $name)) Response::error(400, 'Invalid integration');
        return $name;
    }

    private static function openOrFail(int $uid, string $name): array
    {
        $map = self::open($uid, $name);
        if ($map === null) Response::error(409, 'Vault cannot be opened');
        return $map;
    }

    /** Raw sealed rows of one integration: entry key → stored value. */
    private static function rows(int $uid, string $name): array
    {
        $out = [];
        // Filtered here, not with LIKE: "_" in a name is a LIKE wildcard
        $r = q("SELECT k, v FROM pconfig WHERE uid = %d AND cat = '%s'", intval($uid), dbesc(self::CAT));
        foreach ($r ?: [] as $row) {
            if (str_starts_with($row['k'], "$name:")) $out[substr($row['k'], strlen($name) + 1)] = $row['v'];
        }
        return $out;
    }

    /** Decrypt one stored value to its JSON-decoded array; null when it can't be opened. */
    private static function unseal(int $uid, string $raw): ?array
    {
        $sealed = json_decode($raw, true);
        $ch = channelx_by_n($uid);
        if (!is_array($sealed) || empty($sealed['encrypted']) || empty($ch['channel_prvkey'])) return null;
        $plain = crypto_unencapsulate($sealed, $ch['channel_prvkey']);
        $val = is_string($plain) ? json_decode($plain, true) : null;
        return is_array($val) ? $val : null;
    }

    /** Split a legacy whole-map row into per-entry rows. False when one exists but can't be opened. */
    private static function migrate(int $uid, string $name): bool
    {
        $raw = get_pconfig($uid, self::CAT, $name, '');
        if (!$raw) return true;
        $map = self::unseal($uid, (string) $raw);
        if ($map === null) return false;
        $have = self::rows($uid, $name);
        foreach ($map as $key => $entry) {
            // A per-entry row is newer than the legacy map: never overwrite it
            if (is_array($entry) && !isset($have[$key])) self::put($uid, $name, (string) $key, $entry);
        }
        del_pconfig($uid, self::CAT, $name);
        return true;
    }

    /** The decrypted map; [] when nothing is stored, null when something is but it can't be opened (e.g. rotated keys). */
    public static function open(int $uid, string $name): ?array
    {
        if (!self::migrate($uid, $name)) return null;
        $map = [];
        foreach (self::rows($uid, $name) as $key => $raw) {
            $entry = self::unseal($uid, (string) $raw);
            if ($entry === null) return null;
            $map[$key] = $entry;
        }
        return $map;
    }

    /** Seal and store one entry. */
    public static function put(int $uid, string $name, string $key, array $entry): void
    {
        $ch = channelx_by_n($uid);
        // Never call crypto_encapsulate without a key: core then logs the plaintext
        if (empty($ch['channel_pubkey'])) Response::error(500, 'Channel has no key');
        $sealed = crypto_encapsulate(json_encode((object) $entry), $ch['channel_pubkey']);
        set_pconfig($uid, self::CAT, "$name:$key", json_encode($sealed));
    }

    /** Drop every entry of an integration, openable or not. */
    public static function clear(int $uid, string $name): void
    {
        foreach (array_keys(self::rows($uid, $name)) as $key) del_pconfig($uid, self::CAT, "$name:$key");
        if (get_pconfig($uid, self::CAT, $name, '')) del_pconfig($uid, self::CAT, $name);
    }
}
