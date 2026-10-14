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
 *
 * Owner only. Entries are opaque JSON objects to this handler.
 *
 * Storage: pconfig cat "spa_vault", key = integration, value = the whole map,
 * sealed with the channel's own key pair (core's crypto_encapsulate: a random
 * AES-256 key wrapped by channel_pubkey). Clones share the channel's keys, so
 * every clone can open it; the router's shutdown hook syncs the pconfig write
 * to them like any other. Sealed because core logs whole sync packets at
 * LOGGER_DATA, and pconfig rides backups and table dumps.
 */
class Vault
{
    private const CAT = 'spa_vault';
    private const NAME = '/^[a-z0-9_-]{1,32}$/';
    private const KEY = '/^[A-Za-z0-9._:-]{1,128}$/';
    private const MAX_ENTRIES = 32;
    private const MAX_ENTRY_BYTES = 8192; // client ciphertext + base64

    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $name = self::integration();
        Response::send((object) self::open($uid, $name));
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $name = self::integration();
        $body = Auth::$parsedBody;

        $key = (string) ($body['key'] ?? '');
        if (!preg_match(self::KEY, $key)) Response::error(400, 'Invalid key');

        $map = self::open($uid, $name);
        if (!empty($body['delete'])) {
            unset($map[$key]);
        } else {
            $entry = $body['entry'] ?? null;
            if (!is_array($entry) || array_is_list($entry)) Response::error(400, 'Entry must be an object');
            if (strlen(json_encode($entry)) > self::MAX_ENTRY_BYTES) Response::error(413, 'Entry too large');
            if (!isset($map[$key]) && count($map) >= self::MAX_ENTRIES) Response::error(413, 'Too many entries');
            $map[$key] = $entry;
        }
        self::seal($uid, $name, $map);
        Response::send(['stored' => true]);
    }

    private static function integration(): string
    {
        $name = (string) (\App::$argv[2] ?? '');
        if (!preg_match(self::NAME, $name)) Response::error(400, 'Invalid integration');
        return $name;
    }

    /** The decrypted map; empty when nothing is stored or it can't be opened (e.g. rotated keys). */
    public static function open(int $uid, string $name): array
    {
        $raw = get_pconfig($uid, self::CAT, $name, '');
        if (!$raw) return [];
        $sealed = json_decode((string) $raw, true);
        $ch = channelx_by_n($uid);
        if (!is_array($sealed) || empty($sealed['encrypted']) || empty($ch['channel_prvkey'])) return [];
        $plain = crypto_unencapsulate($sealed, $ch['channel_prvkey']);
        $map = is_string($plain) ? json_decode($plain, true) : null;
        return is_array($map) ? $map : [];
    }

    /**
     * Seal and store. An empty map is stored sealed too (not deleted), so the
     * removal reaches clones as an ordinary synced pconfig write.
     */
    public static function seal(int $uid, string $name, array $map): void
    {
        $ch = channelx_by_n($uid);
        // Never call crypto_encapsulate without a key: core then logs the plaintext
        if (empty($ch['channel_pubkey'])) Response::error(500, 'Channel has no key');
        $sealed = crypto_encapsulate(json_encode((object) $map), $ch['channel_pubkey']);
        set_pconfig($uid, self::CAT, $name, json_encode($sealed));
    }
}
