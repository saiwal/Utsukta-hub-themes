<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\AccessList;
use Zotlabs\Lib\Libsync;
use App;

/**
 * Privacy Groups handler
 *
 * GET    /api/privacy-groups            → list all groups for local channel
 * GET    /api/privacy-groups/:id        → single group + member list
 * GET    /api/privacy-groups/:id/members → contacts NOT in group (for picker)
 * POST   /api/privacy-groups            → create  { name, visible }
 * POST   /api/privacy-groups/:id        → update  { name?, visible?, set_default_acl?, set_default_group? }
 * POST   /api/privacy-groups/:id/toggle → toggle  { xchan_hash }
 * DELETE /api/privacy-groups/:id        → delete
 *
 * Register in Router.php $map:
 *   'privacy-groups' => Handlers\PrivacyGroups::class,
 */
class PrivacyGroups
{
    public function get(): void
    {
        $uid = Auth::requireLocal();
        $this->requireApp($uid);

        $id  = \App::$argv[2] ?? null;
        $sub = \App::$argv[3] ?? null;

        if ($id && $sub === 'members') {
            $this->getAvailableContacts($uid, (int) $id);
        } elseif ($id) {
            $this->getGroup($uid, (int) $id);
        } else {
            $this->listGroups($uid);
        }
    }

    public function post(): void
    {
        $uid  = Auth::requireLocalJson();
        $data = Auth::$parsedBody;
        $this->requireApp($uid);

        $id  = \App::$argv[2] ?? null;
        $sub = \App::$argv[3] ?? null;

        if ($id && $sub === 'toggle') {
            $this->toggleMember($uid, (int) $id, $data);
        } elseif ($id) {
            $this->updateGroup($uid, (int) $id, $data);
        } else {
            $this->createGroup($uid, $data);
        }
    }

    public function delete(): void
    {
        $uid = Auth::requireLocalJson();
        $this->requireApp($uid);

        $id = (int) (\App::$argv[2] ?? 0);
        if (!$id) Response::error(400, 'Missing group id');

        $r = q("SELECT gname FROM pgrp WHERE id = %d AND uid = %d LIMIT 1",
            intval($id), intval($uid));

        if (!$r) Response::error(404, 'Group not found');

        if (!AccessList::remove($uid, $r[0]['gname']))
            Response::error(500, 'Unable to remove privacy group');

        Response::send(['deleted' => true, 'id' => $id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireApp(int $uid): void
    {
        if (!\Zotlabs\Lib\Apps::system_app_installed($uid, 'Privacy Groups'))
            Response::error(403, 'Privacy Groups app is not installed');
    }

    private function listGroups(int $uid): void
    {
        $channel = App::get_channel();
        $rows    = q("SELECT * FROM pgrp WHERE uid = %d AND deleted = 0 ORDER BY gname ASC", intval($uid));
        Response::send(array_map(fn($g) => $this->formatGroup($g, $channel), $rows ?? []));
    }

    private function getGroup(int $uid, int $id): void
    {
        $channel = App::get_channel();
        $r = q("SELECT * FROM pgrp WHERE id = %d AND uid = %d AND deleted = 0 LIMIT 1",
            intval($id), intval($uid));

        if (!$r) Response::error(404, 'Group not found');

        $members = AccessList::members($uid, $id);
        Response::send([
            'group'   => $this->formatGroup($r[0], $channel),
            'members' => array_map([$this, 'formatContact'], $members ?? []),
        ]);
    }

    private function getAvailableContacts(int $uid, int $id): void
    {
        $r = q("SELECT * FROM pgrp WHERE id = %d AND uid = %d AND deleted = 0 LIMIT 1",
            intval($id), intval($uid));
        if (!$r) Response::error(404, 'Group not found');

        $members     = AccessList::members($uid, $id);
        $preselected = array_column($members ?? [], 'xchan_hash');

        $all = q(
            "SELECT abook.*, xchan.*
               FROM abook
               LEFT JOIN xchan ON abook_xchan = xchan_hash
              WHERE abook_channel = %d
                AND abook_self    = 0
                AND abook_blocked = 0
                AND abook_pending = 0
                AND xchan_deleted = 0
              ORDER BY xchan_name ASC",
            intval($uid)
        );

        $available = array_values(array_filter(
            $all ?? [],
            fn($c) => !in_array($c['xchan_hash'], $preselected)
        ));

        Response::send(array_map([$this, 'formatContact'], $available));
    }

    private function createGroup(int $uid, array $data): void
    {
        $name    = notags(trim($data['name'] ?? ''));
        $visible = intval($data['visible'] ?? 0);

        if (!$name) Response::error(400, 'Group name is required');

        $hash = AccessList::add($uid, $name, $visible);
        if (!$hash) Response::error(500, 'Could not create privacy group');

        $r = q("SELECT * FROM pgrp WHERE hash = '%s' AND uid = %d LIMIT 1",
            dbesc($hash), intval($uid));

        Response::send($r ? $this->formatGroup($r[0], App::get_channel()) : ['hash' => $hash]);
    }

    private function updateGroup(int $uid, int $id, array $data): void
    {
        $r = q("SELECT * FROM pgrp WHERE id = %d AND uid = %d AND deleted = 0 LIMIT 1",
            intval($id), intval($uid));
        if (!$r) Response::error(404, 'Group not found');

        $group   = $r[0];
        $name    = notags(trim($data['name'] ?? $group['gname']));
        $visible = isset($data['visible']) ? intval($data['visible']) : intval($group['visible']);

        if ($name !== $group['gname'] || $visible !== intval($group['visible'])) {
            q("UPDATE pgrp SET gname = '%s', visible = %d WHERE uid = %d AND id = %d",
                dbesc($name), intval($visible), intval($uid), intval($id));
        }

        $channel    = App::get_channel();
        $group_hash = $group['hash'];

        $default_group = isset($data['set_default_group'])
            ? ($data['set_default_group'] ? $group_hash : '')
            : $channel['channel_default_group'];

        $default_acl = isset($data['set_default_acl'])
            ? ($data['set_default_acl'] ? '<' . $group_hash . '>' : '')
            : $channel['channel_allow_gid'];

        q("UPDATE channel SET channel_default_group = '%s', channel_allow_gid = '%s' WHERE channel_id = %d",
            dbesc($default_group), dbesc($default_acl), intval($uid));

        Libsync::build_sync_packet($uid, null, true);

        $updated = q("SELECT * FROM pgrp WHERE id = %d AND uid = %d LIMIT 1",
            intval($id), intval($uid));

        Response::send($updated ? $this->formatGroup($updated[0], App::get_channel()) : ['id' => $id]);
    }

    private function toggleMember(int $uid, int $id, array $data): void
    {
        $xchan_hash = $data['xchan_hash'] ?? '';
        if (!$xchan_hash) Response::error(400, 'xchan_hash is required');

        $r = q("SELECT * FROM pgrp WHERE id = %d AND uid = %d AND deleted = 0 LIMIT 1",
            intval($id), intval($uid));
        if (!$r) Response::error(404, 'Group not found');

        $group   = $r[0];
        $members = AccessList::members($uid, $id);
        $hashes  = array_column($members ?? [], 'xchan_hash');

        if (in_array($xchan_hash, $hashes)) {
            AccessList::member_remove($uid, $group['gname'], $xchan_hash);
            $action = 'removed';
        } else {
            AccessList::member_add($uid, $group['gname'], $xchan_hash);
            $action = 'added';
        }

        $members = AccessList::members($uid, $id);
        Response::send([
            'action'  => $action,
            'members' => array_map([$this, 'formatContact'], $members ?? []),
        ]);
    }

    private function formatGroup(array $g, array $channel): array
    {
        return [
            'id'               => intval($g['id']),
            'hash'             => $g['hash'],
            'name'             => $g['gname'],
            'visible'          => (bool) intval($g['visible']),
            'is_default_acl'   => trim($channel['channel_allow_gid'] ?? '', '<>') === $g['hash'],
            'is_default_group' => ($channel['channel_default_group'] ?? '') === $g['hash'],
        ];
    }

    private function formatContact(array $c): array
    {
        return [
            'xchan_hash' => $c['xchan_hash'],
            'name'       => $c['xchan_name'] ?? '',
            'url'        => $c['xchan_url'] ?? '',
            'photo'      => $c['xchan_photo_m'] ?? '',
            'addr'       => $c['xchan_addr'] ?? '',
            'archived'   => (bool) intval($c['abook_archived'] ?? 0),
        ];
    }
}
