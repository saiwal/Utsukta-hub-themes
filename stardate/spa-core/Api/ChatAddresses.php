<?php
// Api/ChatAddresses.php
namespace Utsukta\SpaCore\Api;

use Utsukta\SpaCore\Api\Concerns\FetchesRemoteActor;
use Zotlabs\Lib\Cache;

require_once 'include/channel.php';
require_once 'include/permissions.php';

/**
 * Third-party chat addresses (Matrix, Delta Chat) for a contact, merged from:
 *   private     — your own per-connection vCard IMPP (abconfig system/vcard, core's Connedit data)
 *   addressbook — IMPP of a CardDAV card whose EMAIL/IMPP/URL matches the contact
 *   profile     — a local channel's self-published pconfig spa/chat_ids (view_profile-gated)
 *   actor       — a remote ActivityPub actor's PropertyValue fields (cached 1h)
 * Every value is federated or user-typed, so everything goes through normalize()
 * and anything that doesn't validate is dropped. Links are https:/mailto: only.
 * Docs: src/docs/dev/en/4-features/chat-apps.md
 */
final class ChatAddresses
{
    use FetchesRemoteActor;

    public const TYPES = ['matrix', 'deltachat'];
    private const MXID = '/^@[a-z0-9._=\-\/+]+:[a-z0-9.\-]+(?::\d{1,5})?$/i';
    private const DC_INVITE = '/^https:\/\/i\.delta\.chat\/#[^\s"\'<>]+$/';

    /** One validated address → {type, value, href}, or null. */
    public static function normalize(string $type, string $value): ?array
    {
        $v = trim($value);
        if ($type === 'matrix') {
            $v = preg_replace('#^https://matrix\.to/\#/#', '', $v);
            if (str_starts_with($v, 'matrix:u/')) $v = '@' . substr($v, 9);
            $v = preg_replace('/\?.*$/', '', $v);
            if (!preg_match(self::MXID, $v)) return null;
            return ['type' => 'matrix', 'value' => $v, 'href' => 'https://matrix.to/#/' . $v];
        }
        if ($type === 'deltachat') {
            if (preg_match(self::DC_INVITE, $v)) return ['type' => 'deltachat', 'value' => $v, 'href' => $v];
            $v = preg_replace('/^mailto:/i', '', $v);
            if (!filter_var($v, FILTER_VALIDATE_EMAIL)) return null;
            return ['type' => 'deltachat', 'value' => $v, 'href' => 'mailto:' . $v];
        }
        return null;
    }

    /** Guess the type of a labelled value (actor field, vCard IMPP). */
    public static function classify(string $label, string $value): ?array
    {
        if (preg_match('/delta/i', $label)) return self::normalize('deltachat', $value);
        if (preg_match('/^https:\/\/i\.delta\.chat\//', trim($value))) return self::normalize('deltachat', $value);
        return self::normalize('matrix', $value);
    }

    /** Validate a client-supplied [{type, value}] list; junk is dropped. */
    public static function clean(array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            if (!is_array($i)) continue;
            $n = self::normalize((string) ($i['type'] ?? ''), (string) ($i['value'] ?? ''));
            if ($n) $out[$n['type'] . '|' . $n['value']] = $n;
        }
        return array_values($out);
    }

    /**
     * All addresses for one xchan as seen by $uid (0 = visitor) / $observer.
     * $remote=false skips the network fetch (bulk lists).
     */
    public static function forXchan(int $uid, string $hash, string $observer, bool $remote = true): array
    {
        $x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($hash));
        if (!$x) return [];
        $x = $x[0];

        $found = [];
        $add = function (?array $n, string $source) use (&$found) {
            if ($n && !isset($found[$n['type'] . '|' . $n['value']]))
                $found[$n['type'] . '|' . $n['value']] = $n + ['source' => $source];
        };

        if ($uid) {
            foreach (self::vcardImpps(get_abconfig($uid, $hash, 'system', 'vcard')) as $n) $add($n, 'private');
            foreach (self::addressbookImpps($uid, $x) as $n) $add($n, 'addressbook');
        }

        $channel = channelx_by_hash($hash);
        if ($channel) {
            if (perm_is_allowed($channel['channel_id'], $observer, 'view_profile'))
                foreach (self::own($channel['channel_id']) as $n) $add($n, 'profile');
        } elseif ($remote) {
            foreach ((new self)->actorAddresses($x) as $n) $add($n, 'actor');
        }
        return array_values($found);
    }

    /**
     * Your connections with a known Matrix ID, for the new-DM picker. Cheap
     * sources only — private vCards and local channels' published lists, one
     * query each. ponytail: no addressbook/actor here (per-contact cost); add a
     * cached batch if the picker feels empty.
     */
    public static function matrixForConnections(int $uid, string $observer): array
    {
        $out = [];
        $add = function (string $hash, array $list) use (&$out) {
            foreach ($list as $n) if ($n['type'] === 'matrix') $out[$hash . '|' . $n['value']] = ['xchan' => $hash, 'value' => $n['value']];
        };
        $vcards = q("SELECT xchan, v FROM abconfig WHERE chan = %d AND cat = 'system' AND k = 'vcard'", intval($uid));
        foreach ($vcards ?: [] as $r) $add($r['xchan'], self::vcardImpps($r['v']));
        $locals = q("SELECT c.channel_id, c.channel_hash FROM abook a
                     JOIN channel c ON c.channel_hash = a.abook_xchan
                     JOIN pconfig p ON p.uid = c.channel_id AND p.cat = 'spa' AND p.k = 'chat_ids'
                     WHERE a.abook_channel = %d AND a.abook_self = 0", intval($uid));
        foreach ($locals ?: [] as $r) {
            if (perm_is_allowed($r['channel_id'], $observer, 'view_profile')) $add($r['channel_hash'], self::own(intval($r['channel_id'])));
        }
        if (!$out) return [];
        $hashes = implode("','", array_map('dbesc', array_unique(array_column($out, 'xchan'))));
        $names = [];
        foreach (q("SELECT xchan_hash, xchan_name, xchan_photo_s FROM xchan WHERE xchan_hash IN ('$hashes')") ?: [] as $x)
            $names[$x['xchan_hash']] = $x;
        return array_values(array_map(fn ($e) => $e + [
            'name'  => $names[$e['xchan']]['xchan_name'] ?? '',
            'photo' => $names[$e['xchan']]['xchan_photo_s'] ?? '',
        ], $out));
    }

    /** A channel's own published list. */
    public static function own(int $uid): array
    {
        $v = get_pconfig($uid, 'spa', 'chat_ids');
        return is_array($v) ? self::clean($v) : [];
    }

    public static function setOwn(int $uid, array $items): array
    {
        $clean = self::clean($items);
        set_pconfig($uid, 'spa', 'chat_ids', array_map(fn ($n) => ['type' => $n['type'], 'value' => $n['value']], $clean));
        return $clean;
    }

    /**
     * Replace the matrix/deltachat IMPP entries of your private vCard for a
     * connection, leaving everything else core's Connedit stored untouched.
     */
    public static function setPrivate(int $uid, string $hash, array $items): array
    {
        $clean = self::clean($items);
        $vc = get_abconfig($uid, $hash, 'system', 'vcard');
        $card = $vc ? \Sabre\VObject\Reader::read($vc) : null;
        if (!$card) {
            $x = q("SELECT xchan_name FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($hash));
            $fn = $x[0]['xchan_name'] ?? '';
            $card = new \Sabre\VObject\Component\VCard(['FN' => $fn, 'N' => array_reverse(explode(' ', $fn))]);
        }
        foreach ($card->select('IMPP') as $p) {
            if (self::classifyImpp($p)) $card->remove($p);
        }
        foreach ($clean as $n) $card->add('IMPP', $n['value'], ['type' => $n['type']]);
        set_abconfig($uid, $hash, 'system', 'vcard', $card->serialize());
        return $clean;
    }

    /** encode_person hook: publish a public profile's addresses as AP actor fields. */
    public static function encodePerson(array &$arr): void
    {
        $channel = channelx_by_hash($arr['xchan']['xchan_hash'] ?? '');
        if (!$channel || !perm_is_allowed($channel['channel_id'], '', 'view_profile')) return;
        foreach (self::own($channel['channel_id']) as $n) {
            $arr['encoded']['attachment'][] = [
                'type'  => 'PropertyValue',
                'name'  => $n['type'] === 'matrix' ? 'Matrix' : 'Delta Chat',
                'value' => '<a href="' . htmlspecialchars($n['href'], ENT_QUOTES) . '" rel="me nofollow noopener" target="_blank">'
                    . htmlspecialchars($n['value'], ENT_QUOTES) . '</a>',
            ];
        }
    }

    private static function classifyImpp($p): ?array
    {
        return self::classify((string) ($p['TYPE'] ?? ''), (string) $p);
    }

    private static function vcardImpps($vc): array
    {
        if (!$vc) return [];
        try { $card = \Sabre\VObject\Reader::read($vc); } catch (\Throwable) { return []; }
        $out = [];
        foreach ($card->select('IMPP') as $p) if ($n = self::classifyImpp($p)) $out[] = $n;
        return $out;
    }

    /** IMPP of address-book cards that name this contact by address or url. */
    private static function addressbookImpps(int $uid, array $x): array
    {
        $addr = strtolower((string) $x['xchan_addr']);
        $url = (string) $x['xchan_url'];
        if ($addr === '' && $url === '') return [];
        $nick = q("SELECT channel_address FROM channel WHERE channel_id = %d LIMIT 1", intval($uid));
        if (!$nick) return [];
        // Read-only pre-filter; exact matching happens on the parsed card below.
        $rows = q("SELECT c.carddata FROM cards c JOIN addressbooks a ON a.id = c.addressbookid
                   WHERE a.principaluri = '%s' AND (c.carddata LIKE '%s' OR c.carddata LIKE '%s')",
            dbesc('principals/' . $nick[0]['channel_address']),
            dbesc('%' . ($addr ?: "\x01") . '%'),
            dbesc('%' . ($url ?: "\x01") . '%')
        );
        $strip = fn ($v) => strtolower(preg_replace('/^(mailto|xmpp|acct):/i', '', trim((string) $v)));
        $out = [];
        foreach ($rows ?: [] as $r) {
            $data = is_resource($r['carddata']) ? stream_get_contents($r['carddata']) : $r['carddata'];
            try { $card = \Sabre\VObject\Reader::read($data); } catch (\Throwable) { continue; }
            $match = false;
            foreach (['EMAIL', 'IMPP'] as $prop)
                foreach ($card->select($prop) as $p) if ($addr !== '' && $strip($p) === $addr) $match = true;
            foreach ($card->select('URL') as $p) if ($url !== '' && trim((string) $p) === $url) $match = true;
            if (!$match) continue;
            foreach ($card->select('IMPP') as $p) if ($n = self::classifyImpp($p)) $out[] = $n;
        }
        return $out;
    }

    /** Matrix/Delta Chat fields from a remote AP actor; cached 1h, misses included. */
    private function actorAddresses(array $x): array
    {
        $addr = (string) $x['xchan_addr'];
        if (!str_contains($addr, '@')) return [];
        $key = 'spa:chatids:' . $x['xchan_hash'];
        $hit = Cache::get($key, '1 HOUR');
        if ($hit !== null) return json_decode($hit, true) ?: [];

        $out = [];
        $actor = $this->fetchActorEnrichment($addr, substr($addr, strrpos($addr, '@') + 1), false);
        foreach ($actor['actor_fields'] ?? [] as $f) {
            if (!preg_match('/matrix|delta|chat|element/i', (string) $f['name'])
                && !preg_match('/^(@|https:\/\/(matrix\.to|i\.delta\.chat)\/)/', trim((string) $f['value']))) continue;
            if ($n = self::classify((string) $f['name'], (string) $f['value'])) $out[] = $n;
        }
        Cache::set($key, json_encode($out));
        return $out;
    }
}
