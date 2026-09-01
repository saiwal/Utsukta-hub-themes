<?php
namespace Utsukta\SpaCore\Api\Handlers;

use App;
use DBA;
use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\AccessList;
use Zotlabs\Lib\Apps;

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
        [$type, $item, $url] = $this->resource($uid);

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
            // Guests can still reach it: with no ACL the audience is whatever
            // public_policy says, and a guest holds an abook entry, so
            // 'contacts' and friends already admit them. Ask core rather than
            // reasoning about the policy string ourselves.
            $guests = $this->guestLinks($uid, $type, $item, $url);
            Response::send([
                'scope'  => empty($item['public_policy']) ? 'specific' : $item['public_policy'],
                'access' => [],
                'guests' => $guests,
                'other_guests' => $this->otherGuests($uid, $type, $item, $url, $guests),
                'can_create_guest' => $this->canCreateGuest($uid, $type, $item, $url),
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

        $guests = $this->guestLinks($uid, $type, $item, $url, $allowedXchans);

        // A guest who isn't on the ACL yet is offered as "add" rather than
        // hidden: the owner is standing in the share dialog precisely because
        // they want that person to see this. Only for an already-restricted
        // resource — a public one needs no guest, and writing an allow_cid
        // onto it would silently make it private.
        $others = $this->otherGuests($uid, $type, $item, $url, $guests);

        Response::send([
            'scope'  => $item['public_policy'] ?? '',
            'access' => $access,
            'guests' => $guests,
            'other_guests' => $others,
            'can_create_guest' => $this->canCreateGuest($uid, $type, $item, $url),
        ]);
    }

    /**
     * POST /spa/lockview/:type/:id/grant — body { atoken_id }
     *
     * Adds one of this channel's own guests to the resource's allow_cid, so a
     * ?zat= link for them actually resolves. Federation is not involved: a
     * guest token only authenticates against this hub, so nothing needs
     * re-delivering to remote sites.
     */
    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        [$type, $item, $url] = $this->resource($uid);

        if ((\App::$argv[4] ?? '') !== 'grant') {
            Response::error(404, 'Not found');
        }

        // Refuse where appending to allow_cid would narrow rather than widen —
        // see canGrant().
        if (!$this->canGrant($type, $item)) {
            Response::error(400, 'This is public — a guest link would add nothing');
        }

        $atokenId = intval(Auth::$parsedBody['atoken_id'] ?? 0);
        $token = null;
        foreach ($this->atokens($uid) as $t) {
            if (intval($t['atoken_id']) === $atokenId) {
                $token = $t;
                break;
            }
        }
        if (!$token) {
            Response::error(404, 'Unknown guest');
        }

        $hash  = $token['xchan_hash'];
        $allow = (string) ($item['allow_cid'] ?? '');
        if (!str_contains($allow, '<' . $hash . '>')) {
            $allow .= '<' . $hash . '>';

            foreach ($this->aclTargets($type, $item) as [$table, $where]) {
                q("UPDATE %s SET allow_cid = '%s' WHERE $where",
                    dbesc($table),
                    dbesc($allow)
                );
            }
        }

        Response::send([
            'id'      => $atokenId,
            'name'    => $token['xchan_name'],
            'url'     => $url . (str_contains($url, '?') ? '&' : '?') . 'zat=' . rawurlencode($token['atoken_token']),
            'expires' => $token['atoken_expires'] > DBA::$dba->get_null_date() ? $token['atoken_expires'] : null,
        ]);
    }

    /**
     * Loads and owner-checks the addressed resource.
     *
     * @return array{0:string,1:array,2:string} type, row, public URL
     */
    private function resource(int $uid): array
    {
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

        [$owner, $url] = $this->ownerAndUrl($type, $r[0]);

        // Never disclose (or widen) another channel's audience.
        if (intval($owner) !== $uid) {
            Response::error(403, 'Remote privacy information not available');
        }

        return [$type, $r[0], $url];
    }

    /**
     * The guests who could be added to this resource's audience.
     *
     * @param array $guests the ones already listed with a link
     */
    private function otherGuests(int $uid, string $type, array $item, string $url, array $guests): array
    {
        if (!$url || !$this->canGrant($type, $item)) {
            return [];
        }

        $listed = array_column($guests, 'id');
        $out    = [];
        foreach ($this->atokens($uid) as $t) {
            if (!in_array(intval($t['atoken_id']), $listed, true)) {
                $out[] = ['id' => intval($t['atoken_id']), 'name' => $t['xchan_name']];
            }
        }

        return $out;
    }

    /**
     * May we add a guest to this resource's allow_cid?
     *
     * Only where doing so is additive. Core ORs the ACL branch with the
     * public_policy branch (security.php:496-499), so on an item that is
     * already private an allow_cid entry adds one viewer and takes none away —
     * whether the item names an explicit audience or leans on its policy. On a
     * PUBLIC row the opposite holds: "visible to all" is expressed as an empty
     * allow_cid, so writing one there would drop it out of public view. The
     * other resource types have no item_private flag and no policy branch, so
     * for them an existing allow list is the only safe signal.
     */
    private function canGrant(string $type, array $item): bool
    {
        if ($type === 'item') {
            return !empty($item['item_private']);
        }
        return $this->restricted($item);
    }

    /**
     * May the owner create a brand-new guest for this resource from the share
     * dialog? Same additive test as canGrant(), plus the app that owns the
     * atoken table — Apps::system_app_installed matches the .apd `name:`
     * verbatim and is case-sensitive, so 'Guest Access', never a slug.
     */
    private function canCreateGuest(int $uid, string $type, array $item, string $url): bool
    {
        return $url !== ''
            && $this->canGrant($type, $item)
            && Apps::system_app_installed($uid, 'Guest Access');
    }

    /** True when the row names an explicit audience. */
    private function restricted(array $item): bool
    {
        return strlen($item['allow_cid'] ?? '') > 0 || strlen($item['allow_gid'] ?? '') > 0;
    }

    /**
     * Every row whose allow_cid must move together with this one.
     *
     * A thread carries the ACL on each item, so a guest added to the top post
     * but not its comments would see a conversation with no replies; an image
     * lives in both photo (per scale) and attach, and letting those two drift
     * is the classic "thumbnail visible, full size 403" bug.
     *
     * @return array<array{0:string,1:string}> table and WHERE clause
     */
    private function aclTargets(string $type, array $item): array
    {
        $uid = intval($item['uid'] ?? 0);

        return match ($type) {
            // parent = id matches the thread's comments when $item is the top
            // post, and nothing at all when it is itself a comment.
            'item' => [['item', sprintf('uid = %d AND (id = %d OR parent = %d)', $uid, intval($item['id']), intval($item['id']))]],
            'photo' => [
                ['photo',  sprintf("uid = %d AND resource_id = '%s'", $uid, dbesc($item['resource_id']))],
                ['attach', sprintf("uid = %d AND hash = '%s'", $uid, dbesc($item['resource_id']))],
            ],
            'attach' => [
                ['attach', sprintf('uid = %d AND id = %d', $uid, intval($item['id']))],
                ['photo',  sprintf("uid = %d AND resource_id = '%s'", $uid, dbesc($item['hash']))],
            ],
            'chatroom'  => [['chatroom', sprintf('cr_uid = %d AND cr_id = %d', intval($item['cr_uid']), intval($item['cr_id']))]],
            'menu_item' => [['menu_item', sprintf('mitem_channel_id = %d AND mitem_id = %d', intval($item['mitem_channel_id']), intval($item['mitem_id']))]],
        };
    }

    /**
     * The guests who can already see this resource, each with their ?zat= link.
     *
     * With an explicit ACL that is the classic Lockview test: membership of
     * the expanded allow list. With none ($allowedXchans null — an item whose
     * privacy comes from public_policy alone), the audience is whatever the
     * policy admits, so ask core's scopes_sql instead of re-deriving it.
     *
     * @param string[]|null $allowedXchans expanded allow list, or null to test
     *                                     the item's public_policy instead
     */
    private function guestLinks(int $uid, string $type, array $item, string $url, ?array $allowedXchans = null): array
    {
        if (!$url) {
            return [];
        }

        $out = [];
        foreach ($this->atokens($uid) as $t) {
            $eligible = $allowedXchans === null
                ? $this->policyAdmits($uid, intval($item['id']), $t['xchan_hash'])
                : in_array($t['xchan_hash'], $allowedXchans, true);

            if (!$eligible) {
                continue;
            }
            $out[] = [
                'id'      => intval($t['atoken_id']),
                'name'    => $t['xchan_name'],
                'url'     => $url . (str_contains($url, '?') ? '&' : '?') . 'zat=' . rawurlencode($t['atoken_token']),
                'expires' => $t['atoken_expires'] > DBA::$dba->get_null_date() ? $t['atoken_expires'] : null,
            ];
        }

        return $out;
    }

    /**
     * Does this item's public_policy admit this observer?
     *
     * scopes_sql() rather than item_permissions_sql(): the latter returns an
     * empty string whenever the caller is the channel owner — which we always
     * are here — so it would wave every guest through.
     */
    private function policyAdmits(int $uid, int $id, string $observer): bool
    {
        require_once('include/security.php');

        $sql = \scopes_sql($uid, $observer);
        return (bool) q("SELECT id FROM item WHERE id = %d $sql LIMIT 1", intval($id));
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
                // A guest token only authenticates here, so the link has to
                // point here: an imported feed item's plink is the origin
                // site's URL, where ?zat= means nothing.
                $plink = (string) ($item['plink'] ?? '');
                $local = str_starts_with($plink, \z_root() . '/');
                return [$item['uid'], $local ? $plink : \z_root() . '/item/' . $item['uuid']];
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
