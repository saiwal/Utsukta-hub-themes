<?php
namespace Utsukta\SpaCore\Api\Concerns;

use Zotlabs\Lib\AccessList;
use Zotlabs\Lib\Connect;
use Zotlabs\Lib\Libsync;

/**
 * What a cart "access product" hands its buyer: privacy-group membership
 * and/or a connection from the seller.
 *
 * The storage is the cart addon's (submodules/hzservices.php): a product in
 * pconfig cart-hzservices/skus carries `activate_commands` and
 * `deactivate_commands`, maps of cmdhash => {cmdhash, cmd, params}. We write
 * that exact shape so a product made here is editable in the classic UI and
 * the other way round — but we do not call the addon's hooks to run them:
 * its rollback never assigns $groupname (so a cancel removes nobody), and its
 * fulfil reads App::$profile, which is unset under /spa.
 *
 * Two things differ from the addon on purpose. applyGrants() reports what it
 * actually *changed*, and revokeGrants() undoes only that — so cancelling an
 * order never throws out a buyer who was already in the group, or already a
 * connection, before they bought anything. And `setsvcclass` (admin sellers,
 * classic UI only) is reported as an error to apply by hand rather than run:
 * it writes the account table.
 *
 * Check: CartFulfillment.test.php.
 */
trait CartFulfillment
{
    /**
     * UI grants → [activate_commands, deactivate_commands]. Commands this UI
     * can't express (setsvcclass) are carried over from the old maps.
     *
     * @param array{groups?: string[], connect?: bool} $grants
     */
    protected static function grantsToCommands(array $grants, array $oldActivate = [], array $oldDeactivate = []): array
    {
        $foreign = fn($c) => is_array($c) && !in_array($c['cmd'] ?? '',
            ['addtoprivacygroup', 'rmvfromprivacygroup', 'addconnection', 'rmvconnection'], true);
        $act   = array_filter($oldActivate, $foreign);
        $deact = array_filter($oldDeactivate, $foreign);

        $put = function (array &$map, string $cmd, array $params, string $hashSeed) {
            $h = md5($hashSeed);   // the addon's own cmdhash recipe
            $map[$h] = ['cmdhash' => $h, 'cmd' => $cmd, 'params' => $params];
        };

        foreach (array_unique($grants['groups'] ?? []) as $g) {
            $put($act,   'addtoprivacygroup',   ['group' => $g], 'addtoprivacygroup' . $g);
            $put($deact, 'rmvfromprivacygroup', ['group' => $g], 'rmvfromprivacygroup' . $g);
        }
        if (!empty($grants['connect'])) {
            $put($act,   'addconnection', [], 'addconnection');
            $put($deact, 'rmvconnection', [], 'rmvconnection');
        }
        return [$act, $deact];
    }

    /** The inverse, for the product form. */
    protected static function commandsToGrants(array $activate): array
    {
        $grants = ['groups' => [], 'connect' => false];
        foreach ($activate as $c) {
            if (($c['cmd'] ?? '') === 'addtoprivacygroup' && !empty($c['params']['group'])) {
                $grants['groups'][] = (string) $c['params']['group'];
            } elseif (($c['cmd'] ?? '') === 'addconnection') {
                $grants['connect'] = true;
            }
        }
        return $grants;
    }

    /**
     * Run a product's activate commands for one buyer.
     *
     * @param array $seller channelx row
     * @return array{granted: array{groups: string[], connection: bool}, errors: string[]}
     *         `granted` lists only what this call changed.
     */
    protected static function applyGrants(array $seller, string $buyerXchan, array $activate): array
    {
        $uid     = intval($seller['channel_id']);
        $granted = ['groups' => [], 'connection' => false];
        $errors  = [];

        foreach ($activate as $c) {
            switch ($c['cmd'] ?? '') {
                case 'addtoprivacygroup':
                    $g = AccessList::by_hash($uid, $c['params']['group'] ?? '');
                    if (!$g) { $errors[] = 'Privacy group no longer exists'; break; }
                    $in = q("SELECT id FROM pgrp_member WHERE uid = %d AND gid = %d AND xchan = '%s' LIMIT 1",
                        $uid, intval($g['id']), dbesc($buyerXchan));
                    if ($in) break;
                    if (AccessList::member_add($uid, '', $buyerXchan, intval($g['id']))) {
                        $granted['groups'][] = $g['hash'];
                    } else {
                        $errors[] = 'Could not add buyer to privacy group "' . $g['gname'] . '"';
                    }
                    break;

                case 'addconnection':
                    $has = q("SELECT abook_id FROM abook WHERE abook_channel = %d AND abook_xchan = '%s' LIMIT 1",
                        $uid, dbesc($buyerXchan));
                    if ($has) break;
                    $x = q("SELECT xchan_addr, xchan_url FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($buyerXchan));
                    $r = Connect::connect($seller, $x[0]['xchan_addr'] ?? $x[0]['xchan_url'] ?? '');
                    if (empty($r['success'])) {
                        $errors[] = 'Could not connect to buyer' . (!empty($r['message']) ? ': ' . $r['message'] : '');
                        break;
                    }
                    self::syncAbook($uid, $r['abook']);
                    $granted['connection'] = true;
                    break;

                case 'setsvcclass':
                    $errors[] = 'Service class "' . ($c['params']['class'] ?? '') . '" must be set by hand';
                    break;
            }
        }
        return ['granted' => $granted, 'errors' => $errors];
    }

    /**
     * Undo what applyGrants() reported.
     *
     * ponytail: per-item bookkeeping — if two fulfilled orders granted the same
     * group, only the first recorded it, and cancelling that one removes the
     * buyer while the second is still valid. Reference-count across the
     * buyer's fulfilled items if sellers start overlapping products.
     *
     * @return string[] errors
     */
    protected static function revokeGrants(array $seller, string $buyerXchan, array $granted): array
    {
        $uid    = intval($seller['channel_id']);
        $errors = [];

        foreach ($granted['groups'] ?? [] as $hash) {
            $g = AccessList::by_hash($uid, $hash);
            if ($g) AccessList::member_remove($uid, '', $buyerXchan, intval($g['id']));
        }

        if (!empty($granted['connection'])) {
            $cn = q("SELECT abook_id FROM abook WHERE abook_channel = %d AND abook_self = 0 AND abook_xchan = '%s' LIMIT 1",
                $uid, dbesc($buyerXchan));
            if ($cn) {
                require_once 'include/connections.php';
                if (contact_remove($uid, intval($cn[0]['abook_id']))) {
                    Libsync::build_sync_packet($uid, ['abook' => [['abook_xchan' => $buyerXchan, 'entry_deleted' => true]]]);
                } else {
                    $errors[] = 'Could not remove the connection';
                }
            }
        }
        return $errors;
    }

    /** Clone-sync a new abook row, as core's Follow module does. */
    private static function syncAbook(int $uid, array $abook): void
    {
        $clone = array_filter($abook, fn($k) => str_starts_with($k, 'abook_'), ARRAY_FILTER_USE_KEY);
        unset($clone['abook_id'], $clone['abook_account'], $clone['abook_channel']);
        $abconfig = load_abconfig($uid, $clone['abook_xchan'] ?? '');
        if ($abconfig) $clone['abconfig'] = $abconfig;
        Libsync::build_sync_packet($uid, ['abook' => [$clone]], true);
    }
}
