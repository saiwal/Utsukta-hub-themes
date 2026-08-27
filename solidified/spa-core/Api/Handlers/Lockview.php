<?php
namespace Utsukta\SpaCore\Api\Handlers;

use App;
use DBA;
use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\AccessList;

/**
 * GET /spa/lockview/:type/:id — "who can see this", the JSON port of
 * Zotlabs\Module\Lockview (the padlock dropdown in classic).
 *
 * Also returns the guest-access links for this resource. The eligibility rule
 * is the whole point and is taken verbatim from classic (Lockview.php:230): a
 * `?zat=<token>` link is only offered for a guest whose xchan is ALREADY in
 * this item's allow list. A token is not a skeleton key — it logs the visitor
 * in as a pseudo-contact and the normal ACL still decides what they see — so
 * handing out a link for a guest who isn't on the ACL would just produce a
 * "not found" for the recipient, and handing out one for an item the caller
 * doesn't own would leak someone else's audience.
 */
class Lockview
{
    private const TYPES = ['item', 'photo', 'attach', 'menu_item', 'chatroom'];

    public function get(): void
    {
        $uid = Auth::requireLocalGet();

        $type = \App::$argv[2] ?? '';
        $ref  = (string) (\App::$argv[3] ?? '');

        if (!in_array($type, self::TYPES, true) || $ref === '') {
            Response::error(404, 'Not found');
        }

        // Photos are addressed by resource_id everywhere in the SPA, and the
        // photo table holds one row per image scale sharing that id (and the
        // same ACL), so match on the hash and take any one row.
        if ($type === 'photo' && !ctype_digit($ref)) {
            $r = q("SELECT * FROM photo WHERE resource_id = '%s' LIMIT 1", dbesc($ref));
        } else {
            if (!ctype_digit($ref)) {
                Response::error(404, 'Not found');
            }
            $idCol = match ($type) {
                'menu_item' => 'mitem_id',
                'chatroom'  => 'cr_id',
                default     => 'id',
            };
            $r = q("SELECT * FROM %s WHERE $idCol = %d LIMIT 1", dbesc($type), intval($ref));
        }

        if (!$r) {
            Response::error(404, 'Not found');
        }
        $item = $r[0];

        [$owner, $url] = $this->ownerAndUrl($type, $item);

        // Never disclose another channel's audience.
        if (intval($owner) !== $uid) {
            Response::error(403, 'Remote privacy information not available');
        }

        // Private with no explicit ACL. Several things land here: a feed
        // (RSS) item, a private-to-self item, or a post we received as a bcc /
        // targeted recipient without the visibility list. There is no audience
        // to enumerate, so report the scope the same way classic does
        // (Lockview.php:101-110) — a human label from translate_scope(), not a
        // "we don't know" message.
        if (!empty($item['item_private']) && !strlen($item['allow_cid'] ?? '') && !strlen($item['allow_gid'] ?? '')
            && !strlen($item['deny_cid'] ?? '') && !strlen($item['deny_gid'] ?? '')) {
            // The raw scope only — the SPA maps it to a label in the viewer's
            // own locale (scopeLabel() in lib/lockview-api.ts), rather than
            // core's translate_scope() which renders in the channel's language.
            Response::send([
                'scope'  => empty($item['public_policy']) ? 'specific' : $item['public_policy'],
                'access' => [],
                'guests' => [],
                'no_audience' => true,
            ]);
        }

        $allowedUsers  = \expand_acl($item['allow_cid'] ?? '');
        $allowedGroups = \expand_acl($item['allow_gid'] ?? '');
        $denyUsers     = \expand_acl($item['deny_cid'] ?? '');
        $denyGroups    = \expand_acl($item['deny_gid'] ?? '');

        $access         = [];
        $allowedXchans  = [];

        // Privacy groups and profile ("vp.") groups both expand to member
        // xchans — that expansion is how a guest added to a privacy group
        // becomes eligible without being named in allow_cid directly.
        foreach ([[$allowedGroups, false], [$denyGroups, true]] as [$groups, $denied]) {
            [$profileGuids, $groupHashes] = $this->splitGroups($groups);

            if ($profileGuids) {
                // Scoped to this channel and deduped by guid: clone sync can
                // leave several rows sharing one guid, and classic's unscoped
                // query renders each of them as a separate audience entry.
                $rows = q("SELECT MIN(id) AS id, profile_name FROM profile
                           WHERE uid = %d AND profile_guid IN (" . implode(',', $profileGuids) . ")
                           GROUP BY profile_guid, profile_name",
                    intval($uid)
                );
                foreach ($rows ?: [] as $row) {
                    if (!$denied) {
                        $allowedXchans = array_merge($allowedXchans, AccessList::profile_members_xchan($uid, $row['id']));
                    }
                    $access[] = ['kind' => 'profile', 'name' => $row['profile_name'], 'denied' => $denied];
                }
            }

            if ($groupHashes) {
                $rows = q("SELECT MIN(id) AS id, gname FROM pgrp
                           WHERE uid = %d AND hash IN (" . implode(',', $groupHashes) . ")
                           GROUP BY hash, gname",
                    intval($uid)
                );
                foreach ($rows ?: [] as $row) {
                    if (!$denied) {
                        $allowedXchans = array_merge($allowedXchans, AccessList::members_xchan($uid, $row['id']));
                    }
                    $access[] = ['kind' => 'group', 'name' => $row['gname'], 'denied' => $denied];
                }
            }
        }

        $atokens      = $this->atokens($uid);
        $atokenHashes = array_column($atokens, 'xchan_hash');

        foreach ([[$allowedUsers, false], [$denyUsers, true]] as [$users, $denied]) {
            $hashes = array_values(array_filter($users, fn($u) => !str_starts_with(trim($u, "'"), 'token:')));
            if (!$hashes) {
                continue;
            }
            \stringify_array_elms($hashes, true);
            $rows = q("SELECT xchan_name, xchan_hash FROM xchan WHERE xchan_hash IN (" . implode(',', $hashes) . ")");
            foreach ($rows ?: [] as $row) {
                if (!$denied) {
                    $allowedXchans[] = $row['xchan_hash'];
                }
                // Guests are listed in their own section below, not twice.
                if (!in_array($row['xchan_hash'], $atokenHashes, true)) {
                    $access[] = ['kind' => 'contact', 'name' => $row['xchan_name'], 'denied' => $denied];
                }
            }
        }

        $allowedXchans = array_unique($allowedXchans);

        $guests = [];
        if ($url) {
            foreach ($atokens as $t) {
                if (!in_array($t['xchan_hash'], $allowedXchans, true)) {
                    continue;
                }
                $guests[] = [
                    'id'      => intval($t['atoken_id']),
                    'name'    => $t['xchan_name'],
                    'url'     => $url . (str_contains($url, '?') ? '&' : '?') . 'zat=' . rawurlencode($t['atoken_token']),
                    'expires' => $t['atoken_expires'] > DBA::$dba->get_null_date() ? $t['atoken_expires'] : null,
                ];
            }
        }

        Response::send([
            'scope'  => $item['public_policy'] ?? '',
            'access' => $access,
            'guests' => $guests,
        ]);
    }

    /**
     * Guest tokens for this channel, expired ones dropped.
     *
     * Core only reaps expired tokens in Daemon/Cron (Cron.php:96-105) and
     * zat_init() itself never checks atoken_expires, so an expired token still
     * works until cron next runs. Offering a link for one would be handing out
     * access that is supposed to be over, so filter here.
     */
    private function atokens(int $uid): array
    {
        require_once('include/security.php');

        $rows = q("SELECT * FROM atoken WHERE atoken_uid = %d", intval($uid));
        $out  = [];
        $null = DBA::$dba->get_null_date();

        foreach ($rows ?: [] as $row) {
            if ($row['atoken_expires'] > $null && strtotime($row['atoken_expires'] . 'Z') < time()) {
                continue;
            }
            $xchan = \atoken_xchan($row);
            if ($xchan) {
                $out[] = array_merge($row, $xchan);
            }
        }

        return $out;
    }

    /** Splits an expanded allow/deny_gid list into profile guids and pgrp hashes. */
    private function splitGroups(array $groups): array
    {
        $profiles = [];
        $hashes   = [];
        foreach ($groups as $g) {
            if (str_starts_with($g, 'vp.')) {
                $profiles[] = "'" . dbesc(substr($g, 3)) . "'";
            } else {
                $hashes[] = "'" . dbesc($g) . "'";
            }
        }
        return [$profiles, $hashes];
    }

    /** @return array{0:int|null,1:string} owner channel id and the resource's public URL */
    private function ownerAndUrl(string $type, array $item): array
    {
        switch ($type) {
            case 'menu_item':
                return [$item['mitem_channel_id'], ''];
            case 'chatroom':
                $channel = \channelx_by_n($item['cr_uid']);
                return [$item['cr_uid'], \z_root() . '/chat/' . $channel['channel_address'] . '/' . $item['cr_id']];
            case 'item':
                return [$item['uid'], $item['plink']];
            case 'photo':
                $channel = \channelx_by_n($item['uid']);
                return [$item['uid'], \z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $item['resource_id']];
            case 'attach':
                $channel = \channelx_by_n($item['uid']);
                return [$item['uid'], \z_root() . '/cloud/' . $channel['channel_address'] . '/' . $item['display_path']];
        }
        return [null, ''];
    }
}
