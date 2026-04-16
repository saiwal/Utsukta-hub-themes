<?php
namespace Zotlabs\Module;

use App;

class Manage_api extends \Zotlabs\Web\Controller
{
    function get()
    {
        if (($_GET['format'] ?? '') !== 'json')
            return;

        if (!get_account_id() || (isset($_SESSION['delegate']) && $_SESSION['delegate'])) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        require_once('include/security.php');

        $account = \App::get_account();
        $current_uid = local_channel();

        // ── Channel list (mirrors Manage::get() exactly) ──────────────────────
        $r = q("SELECT channel.*, xchan.* FROM channel
                LEFT JOIN xchan ON channel.channel_hash = xchan.xchan_hash
                WHERE channel.channel_account_id = %d
                  AND channel_removed = 0
                ORDER BY channel_name",
            intval(get_account_id())
        );

        $channels = [];
        foreach (($r ?: []) as $ch) {
            // Pending intro count
            $intr = q("SELECT COUNT(abook.abook_id) AS total FROM abook
                       LEFT JOIN xchan ON abook.abook_xchan = xchan.xchan_hash
                       WHERE abook_channel = %d
                         AND abook_pending = 1
                         AND abook_self = 0
                         AND abook_ignored = 0
                         AND xchan_deleted = 0
                         AND xchan_orphan = 0",
                intval($ch['channel_id'])
            );

            $channels[] = [
                'channel_id'      => intval($ch['channel_id']),
                'channel_name'    => $ch['channel_name'],
                'channel_address' => $ch['channel_address'],
                'channel_hash'    => $ch['channel_hash'],
                'is_current'      => (intval($ch['channel_id']) === intval($current_uid)),
                'is_default'      => (intval($ch['channel_id']) === intval($account['account_default_channel'])),
                'photo'           => $ch['xchan_photo_m'] ?? '',
                'url'             => $ch['xchan_url'] ?? '',
                'intros'          => intval($intr[0]['total'] ?? 0),
                'switch_url'      => z_root() . '/manage/' . intval($ch['channel_id']),
                'make_default_url'=> z_root() . '/manage/' . intval($ch['channel_id']) . '/default',
            ];
        }

        // ── Channel count / limit ─────────────────────────────────────────────
        $count_r = q("SELECT COUNT(channel_id) AS total FROM channel
                      WHERE channel_account_id = %d AND channel_removed = 0",
            intval(get_account_id())
        );
        $total_channels = intval($count_r[0]['total'] ?? 0);
        $limit = account_service_class_fetch(get_account_id(), 'total_identities');

        // ── Delegates ─────────────────────────────────────────────────────────
        $delegates = [];
        if ($current_uid) {
            $d = q("SELECT * FROM abook
                    LEFT JOIN xchan ON abook_xchan = xchan_hash
                    WHERE abook_channel = %d
                      AND abook_xchan IN (
                          SELECT xchan FROM abconfig
                          WHERE chan = %d
                            AND cat = 'their_perms'
                            AND k = 'delegate'
                            AND v = '1'
                      )",
                intval($current_uid),
                intval($current_uid)
            );

            foreach (($d ?: []) as $del) {
                $delegates[] = [
                    'name'       => $del['xchan_name'],
                    'address'    => $del['xchan_addr'],
                    'photo'      => $del['xchan_photo_m'] ?? '',
                    'url'        => $del['xchan_url'] ?? '',
                    'switch_url' => z_root() . '/magic?f=&bdest='
                        . bin2hex($del['xchan_url'] . '?zid=' . get_my_address()
                            . '&delegate=' . urlencode($del['xchan_addr']))
                        . '&delegate=' . urlencode($del['xchan_addr']),
                ];
            }
        }

        json_return_and_die([
            'channels'       => $channels,
            'delegates'      => $delegates,
            'current_uid'    => intval($current_uid),
            'total_channels' => $total_channels,
            'limit'          => $limit !== false ? intval($limit) : null,
            'create_url'     => z_root() . '/new_channel',
        ]);
    }

    function post()
    {
        if (($_GET['format'] ?? '') !== 'json')
            return;

        if (!get_account_id() || (isset($_SESSION['delegate']) && $_SESSION['delegate'])) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            json_return_and_die(['error' => 'Invalid JSON body']);
        }

        // Switch channel
        if (!empty($data['switch_to'])) {
            $target_uid = intval($data['switch_to']);

            // Verify this channel belongs to the account
            $r = q("SELECT channel_id FROM channel
                    WHERE channel_id = %d
                      AND channel_account_id = %d
                    LIMIT 1",
                $target_uid,
                intval(get_account_id())
            );
            if (!$r) {
                json_return_and_die(['error' => 'Channel not found']);
            }

            $result = change_channel($target_uid);
            if (!$result) {
                json_return_and_die(['error' => 'Channel switch failed']);
            }

            json_return_and_die([
                'status'      => 'ok',
                'channel_id'  => $target_uid,
                'redirect_to' => $result['channel_startpage']
                    ? z_root() . '/' . $result['channel_startpage']
                    : z_root() . '/hq',
            ]);
        }

        // Set default channel
        if (!empty($data['set_default'])) {
            $target_uid = intval($data['set_default']);

            $r = q("SELECT channel_id FROM channel
                    WHERE channel_id = %d
                      AND channel_account_id = %d
                    LIMIT 1",
                $target_uid,
                intval(get_account_id())
            );
            if (!$r) {
                json_return_and_die(['error' => 'Channel not found']);
            }

            q("UPDATE account SET account_default_channel = %d WHERE account_id = %d",
                $target_uid,
                intval(get_account_id())
            );

            json_return_and_die(['status' => 'ok', 'default_channel_id' => $target_uid]);
        }

        json_return_and_die(['error' => 'Unknown action']);
    }
}
