<?php
namespace Utsukta\SpaCore\Api\Handlers;

use App;
use DBA;
use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\AccessList;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Permcat;

/**
 * Guest access tokens — the JSON port of Zotlabs\Module\Tokens.
 *
 * A guest token is NOT a skeleton key. Creating one also creates a real xchan
 * and abook row (hash: substr(channel_hash,0,16) . '.' . atoken_guid), so the
 * guest behaves as an ordinary pseudo-contact: it shows up in the ACL picker
 * (core's /acl unions atokens with abook) and only sees what its channel's
 * permissions and each item's ACL actually allow. Arriving at `?zat=<token>`
 * just logs you in as that contact — see zat_init() in include/channel.php.
 *
 * That means the abook row is not optional bookkeeping: skip it and the token
 * becomes ungrantable, because nothing would list it as a possible recipient.
 *
 *   GET  /spa/tokens            → list
 *   GET  /spa/tokens/new        → { token } — a fresh password to prefill the form
 *   POST /spa/tokens            → create { name, token, expires?, role? }
 *   POST /spa/tokens/:id        → update { name, token, expires?, role? }
 *   POST /spa/tokens/:id/delete → delete
 */
class Tokens
{
    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $this->requireApp($uid);

        if ((\App::$argv[2] ?? '') === 'new') {
            Response::send(['token' => \new_token()]);
        }

        $channel = \App::get_channel();
        $rows = q("SELECT * FROM atoken WHERE atoken_uid = %d ORDER BY atoken_name ASC",
            intval($uid)
        );

        $tokens = array_map(fn($r) => $this->format($r, $channel), $rows ?: []);

        Response::send($tokens, [
            'roles'   => $this->roles($uid),
            'quota'   => $this->quota($uid),
        ]);
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $this->requireApp($uid);

        $sub = \App::$argv[2] ?? '';
        $id  = ctype_digit((string) $sub) ? intval($sub) : 0;

        if ($id && (\App::$argv[3] ?? '') === 'delete') {
            $this->delete($uid, $id);
        }

        $this->save($uid, $id);
    }

    // ── read helpers ─────────────────────────────────────────────────────────

    private function format(array $row, array $channel): array
    {
        $guid = substr($channel['channel_hash'], 0, 16) . '.' . $row['atoken_guid'];

        $abook = q("SELECT abook_role FROM abook WHERE abook_channel = %d AND abook_xchan = '%s' LIMIT 1",
            intval($channel['channel_id']),
            dbesc($guid)
        );

        $nullDate = DBA::$dba->get_null_date();

        return [
            'id'         => intval($row['atoken_id']),
            'name'       => $row['atoken_name'],
            'token'      => $row['atoken_token'],
            // A guest logs in with name + token at the ordinary login form
            // (include/auth.php), so surface the address form the same way
            // atoken_xchan() does.
            'guest_addr' => 'guest:' . $row['atoken_name'] . '@' . \App::get_hostname(),
            'xchan_hash' => $guid,
            'expires'    => ($row['atoken_expires'] > $nullDate) ? $row['atoken_expires'] : null,
            'expired'    => $this->isExpired($row['atoken_expires']),
            'role'       => $abook[0]['abook_role'] ?? '',
        ];
    }

    private function isExpired(?string $expires): bool
    {
        if (!$expires || $expires <= DBA::$dba->get_null_date()) {
            return false;
        }
        return strtotime($expires . 'Z') < time();
    }

    private function roles(int $uid): array
    {
        $pcat = new Permcat($uid);
        return array_map(fn($pc) => [
            'name'   => $pc['name'],
            'label'  => $pc['localname'],
            'system' => (bool) intval($pc['system']),
        ], $pcat->listing());
    }

    private function quota(int $uid): array
    {
        $max = \service_class_fetch($uid, 'access_tokens');
        $r = q("SELECT COUNT(atoken_id) AS total FROM atoken WHERE atoken_uid = %d", intval($uid));
        return [
            'used'  => intval($r[0]['total'] ?? 0),
            'limit' => $max ? intval($max) : null,
        ];
    }

    // ── writes ───────────────────────────────────────────────────────────────

    private function save(int $uid, int $atoken_id): never
    {
        $channel = \App::get_channel();
        $body    = Auth::$parsedBody;

        $name  = \notags(trim((string) ($body['name'] ?? '')));
        $token = trim((string) ($body['token'] ?? ''));
        $role  = \notags(trim((string) ($body['role'] ?? '')));

        if ($name === '' || $token === '') {
            Response::error(400, 'Login name and password are required');
        }
        // The name doubles as a login identifier (auth.php matches atoken_name
        // against the login field), and a '@' would make it look like an
        // account email and never match.
        if (strpos($name, '@') !== false) {
            Response::error(400, 'Login name may not contain @');
        }

        $expires = $this->parseExpires($body['expires'] ?? null);

        // Names must stay unique per channel — auth.php looks a guest up by
        // name + token alone, so duplicates make logins ambiguous.
        $clash = q("SELECT atoken_id FROM atoken WHERE atoken_uid = %d AND atoken_name = '%s' AND atoken_id != %d LIMIT 1",
            intval($uid),
            dbesc($name),
            intval($atoken_id)
        );
        if ($clash) {
            Response::error(409, 'A guest with that login name already exists');
        }

        if (!$atoken_id) {
            $quota = $this->quota($uid);
            if ($quota['limit'] !== null && $quota['used'] >= $quota['limit']) {
                Response::error(403, sprintf('This channel is limited to %d guest tokens', $quota['limit']));
            }
        }

        if ($atoken_id) {
            $existing = q("SELECT * FROM atoken WHERE atoken_id = %d AND atoken_uid = %d LIMIT 1",
                intval($atoken_id),
                intval($uid)
            );
            if (!$existing) {
                Response::error(404, 'Token not found');
            }

            q("UPDATE atoken SET atoken_name = '%s', atoken_token = '%s', atoken_expires = '%s'
               WHERE atoken_id = %d AND atoken_uid = %d",
                dbesc($name),
                dbesc($token),
                dbesc($expires),
                intval($atoken_id),
                intval($uid)
            );
            $guid = $existing[0]['atoken_guid'];
        } else {
            $guid = \new_uuid();
            q("INSERT INTO atoken (atoken_guid, atoken_aid, atoken_uid, atoken_name, atoken_token, atoken_expires)
               VALUES ('%s', %d, %d, '%s', '%s', '%s')",
                dbesc($guid),
                intval($channel['channel_account_id']),
                intval($uid),
                dbesc($name),
                dbesc($token),
                dbesc($expires)
            );
        }

        $atok = q("SELECT * FROM atoken WHERE atoken_uid = %d AND atoken_guid = '%s' LIMIT 1",
            intval($uid),
            dbesc($guid)
        );
        if (!$atok) {
            Response::error(500, 'Token could not be saved');
        }

        require_once('include/security.php');
        $xchan = \atoken_xchan($atok[0]);
        \atoken_create_xchan($xchan);
        $atoken_xchan = $xchan['xchan_hash'];

        // A rename has to follow through to the shadow xchan, or the guest
        // keeps showing under its old name everywhere it is already granted.
        q("UPDATE xchan SET xchan_name = '%s', xchan_addr = '%s' WHERE xchan_hash = '%s'",
            dbesc($xchan['xchan_name']),
            dbesc($xchan['xchan_addr']),
            dbesc($atoken_xchan)
        );

        if (!$atoken_id) {
            $this->createAbook($channel, $atoken_xchan);
        }

        Permcat::assign($channel, $role, [$atoken_xchan]);

        $this->syncClone($channel, $atoken_xchan, $atok);

        Response::send($this->format($atok[0], $channel));
    }

    private function createAbook(array $channel, string $atoken_xchan): void
    {
        $closeness      = get_pconfig($channel['channel_id'], 'system', 'new_abook_closeness', 80);
        $profile_assign = get_pconfig($channel['channel_id'], 'system', 'profile_assign', '');

        $ok = \abook_store_lowlevel([
            'abook_account'   => intval($channel['channel_account_id']),
            'abook_channel'   => intval($channel['channel_id']),
            'abook_closeness' => intval($closeness),
            'abook_xchan'     => $atoken_xchan,
            'abook_profile'   => $profile_assign,
            'abook_feed'      => 0,
            'abook_created'   => datetime_convert(),
            'abook_updated'   => datetime_convert(),
            'abook_instance'  => \z_root(),
        ]);

        if (!$ok) {
            logger('spa/tokens: abook creation failed for ' . $atoken_xchan);
        }

        if ($channel['channel_default_group']) {
            $g = AccessList::by_hash($channel['channel_id'], $channel['channel_default_group']);
            if ($g) {
                AccessList::member_add($channel['channel_id'], '', $atoken_xchan, $g['id']);
            }
        }
    }

    private function syncClone(array $channel, string $atoken_xchan, array $atok, bool $deleted = false): void
    {
        $r = q("SELECT abook.*, xchan.* FROM abook LEFT JOIN xchan ON abook_xchan = xchan_hash
                WHERE abook_channel = %d AND abook_xchan = '%s' LIMIT 1",
            intval($channel['channel_id']),
            dbesc($atoken_xchan)
        );
        if (!$r) {
            return;
        }

        $clone = $r[0];
        unset($clone['abook_id'], $clone['abook_account'], $clone['abook_channel']);

        $abconfig = \load_abconfig($channel['channel_id'], $clone['abook_xchan']);
        if ($abconfig) {
            $clone['abconfig'] = $abconfig;
        }
        if ($deleted) {
            $clone['deleted'] = true;
        }

        Libsync::build_sync_packet($channel['channel_id'], ['abook' => [$clone], 'atoken' => $atok], true);
    }

    private function delete(int $uid, int $atoken_id): never
    {
        $channel = \App::get_channel();

        $r = q("SELECT * FROM atoken WHERE atoken_id = %d AND atoken_uid = %d LIMIT 1",
            intval($atoken_id),
            intval($uid)
        );
        if (!$r) {
            Response::error(404, 'Token not found');
        }

        $atoken = $r[0];
        $atoken_xchan = substr($channel['channel_hash'], 0, 16) . '.' . $atoken['atoken_guid'];

        // Build the clone packet from the still-present abook row, then delete.
        $atoken['deleted'] = true;
        $this->syncClone($channel, $atoken_xchan, [$atoken], true);

        require_once('include/security.php');
        \atoken_delete($atoken_id);

        Response::send(['status' => 'ok']);
    }

    /** Accepts a yyyy-mm-dd (or full datetime) local date; '' means never. */
    private function parseExpires($raw): string
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return DBA::$dba->get_null_date();
        }
        if (strtotime($raw) === false) {
            Response::error(400, 'Expiry date is not a valid date');
        }
        return datetime_convert(date_default_timezone_get(), 'UTC', $raw);
    }

    private function requireApp(int $uid): void
    {
        // Apps::system_app_installed() matches the .apd `name:` verbatim and is
        // case-sensitive — 'Guest Access', never a slug (app/tokens.apd).
        if (!Apps::system_app_installed($uid, 'Guest Access')) {
            Response::error(403, 'Guest Access app is not installed');
        }
    }
}
