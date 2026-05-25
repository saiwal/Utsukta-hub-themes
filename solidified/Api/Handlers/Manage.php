<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Manage
{
    // ── GET /api/manage ───────────────────────────────────────────────────────

    public function get(): void
    {
        $this->requireManageAccess();

        require_once 'include/security.php';

        $account = \App::get_account();
        $current_uid = local_channel();

        Response::send([
            'channels' => $this->buildChannelList($account, $current_uid),
            'delegates' => $this->buildDelegateList($current_uid),
            'current_uid' => intval($current_uid),
            'total_channels' => $this->countChannels(),
            'limit' => $this->channelLimit(),
            'create_url' => z_root() . '/new_channel',
        ]);
    }

    // ── POST /api/manage ──────────────────────────────────────────────────────

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $this->requireManageAccess();

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            Response::error(400, 'Invalid JSON body');
        }

        if (!empty($body['switch_to'])) {
            $this->switchChannel(intval($body['switch_to']));
        }

        if (!empty($body['set_default'])) {
            $this->setDefaultChannel(intval($body['set_default']));
        }

        Response::error(400, 'Unknown action');
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    private function requireManageAccess(): void
    {
        if (!get_account_id() || !empty($_SESSION['delegate'])) {
            Response::error(403, 'Permission denied');
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    private function switchChannel(int $targetUid): never
    {
        $this->verifyChannelOwnership($targetUid);

        $result = change_channel($targetUid);
        if (!$result) {
            Response::error(500, 'Channel switch failed');
        }

        $redirectTo = !empty($result['channel_startpage'])
            ? z_root() . '/' . $result['channel_startpage']
            : z_root() . '/hq';

        Response::send([
            'channel_id' => $targetUid,
            'redirect_to' => $redirectTo,
        ]);
    }

    private function setDefaultChannel(int $targetUid): never
    {
        $this->verifyChannelOwnership($targetUid);

        q('UPDATE account SET account_default_channel = %d WHERE account_id = %d',
            $targetUid,
            intval(get_account_id()));

        Response::send(['default_channel_id' => $targetUid]);
    }

    // ── Builders ──────────────────────────────────────────────────────────────

    private function buildChannelList(array $account, int $currentUid): array
    {
        $rows = q('SELECT channel.*, xchan.* FROM channel
                   LEFT JOIN xchan ON channel.channel_hash = xchan.xchan_hash
                   WHERE channel.channel_account_id = %d
                     AND channel_removed = 0
                   ORDER BY channel_name',
            intval(get_account_id()));

        $channels = [];
        foreach (($rows ?: []) as $ch) {
            $intr = q('SELECT COUNT(abook.abook_id) AS total FROM abook
                       LEFT JOIN xchan ON abook.abook_xchan = xchan.xchan_hash
                       WHERE abook_channel = %d
                         AND abook_pending = 1
                         AND abook_self = 0
                         AND abook_ignored = 0
                         AND xchan_deleted = 0
                         AND xchan_orphan = 0',
                intval($ch['channel_id']));

            $channels[] = [
                'channel_id' => intval($ch['channel_id']),
                'channel_name' => $ch['channel_name'],
                'channel_address' => $ch['channel_address'],
                'channel_hash' => $ch['channel_hash'],
                'is_current' => intval($ch['channel_id']) === $currentUid,
                'is_default' => intval($ch['channel_id']) === intval($account['account_default_channel']),
                'photo' => $ch['xchan_photo_m'] ?? '',
                'url' => $ch['xchan_url'] ?? '',
                'intros' => intval($intr[0]['total'] ?? 0),
            ];
        }

        return $channels;
    }

    private function buildDelegateList(int $currentUid): array
    {
        if (!$currentUid)
            return [];

        $rows = q("SELECT * FROM abook
                   LEFT JOIN xchan ON abook_xchan = xchan_hash
                   WHERE abook_channel = %d
                     AND abook_xchan IN (
                         SELECT xchan FROM abconfig
                         WHERE chan = %d
                           AND cat = 'their_perms'
                           AND k = 'delegate'
                           AND v = '1'
                     )",
            intval($currentUid),
            intval($currentUid));

        $delegates = [];
        foreach (($rows ?: []) as $del) {
            $delegates[] = [
                'name' => $del['xchan_name'],
                'address' => $del['xchan_addr'],
                'photo' => $del['xchan_photo_m'] ?? '',
                'url' => $del['xchan_url'] ?? '',
                // switch_url computed client-side from address — avoids
                // exposing get_my_address() output in every response
            ];
        }

        return $delegates;
    }

    private function countChannels(): int
    {
        $r = q('SELECT COUNT(channel_id) AS total FROM channel
                WHERE channel_account_id = %d AND channel_removed = 0',
            intval(get_account_id()));
        return intval($r[0]['total'] ?? 0);
    }

    private function channelLimit(): int|null
    {
        $limit = account_service_class_fetch(get_account_id(), 'total_identities');
        return $limit !== false ? intval($limit) : null;
    }

    private function verifyChannelOwnership(int $targetUid): void
    {
        $r = q('SELECT channel_id FROM channel
                WHERE channel_id = %d
                  AND channel_account_id = %d
                LIMIT 1',
            $targetUid,
            intval(get_account_id()));
        if (!$r) {
            Response::error(404, 'Channel not found');
        }
    }
}
