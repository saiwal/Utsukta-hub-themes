<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;

/**
 * GET /spa/delivery — how the channel's outgoing deliveries fared, from the
 * delivery reports core keeps (dreport, pruned daily after
 * system/expire_delivery_reports days, 10 by default — so "recent" is all
 * there is). Sites that keep failing are usually dead or blocking us; the
 * contact list's Unreachable filter is the same story from the other end.
 *
 * dreport_result is free text set all over core; rather than enumerate every
 * failure, the handful of success/pending strings are matched and anything
 * else counts as failed.
 */
class Delivery
{
    private const OK      = ['posted', 'updated', 'update ignored', 'relayed', 'deleted', 'delivered',
                             'mail delivered', 'channel sync', 'self delivery ignored', 'accepted',
                             // already there — a re-delivery, not a loss
                             'storage failed: duplicate'];
    private const PENDING = ['queued'];

    public function get(): void
    {
        Auth::requireLocalGet();
        $hash = \App::get_channel()['channel_hash'];

        $r = q("SELECT dreport_site, dreport_result, dreport_time FROM dreport
                WHERE dreport_xchan = '%s' ORDER BY dreport_time DESC LIMIT 5000",
            dbesc($hash)) ?: [];

        $counts = ['ok' => 0, 'pending' => 0, 'failed' => 0];
        $sites  = [];
        foreach ($r as $row) {
            $kind = $this->classify($row['dreport_result']);
            $counts[$kind]++;
            if ($kind !== 'failed') continue;
            // dreport_site is a bare host for zot, an inbox URL for ActivityPub.
            $site = parse_url($row['dreport_site'], PHP_URL_HOST) ?: $row['dreport_site'];
            $sites[$site] ??= ['site' => $site, 'failed' => 0, 'last_result' => $row['dreport_result'], 'last_time' => $row['dreport_time']];
            $sites[$site]['failed']++;
        }
        usort($sites, fn($a, $b) => $b['failed'] <=> $a['failed']);

        Response::send($counts + [
            'total'  => count($r),
            'oldest' => $r ? end($r)['dreport_time'] : null,
            'sites'  => array_slice(array_map(fn($s) => ['last_result' => mb_substr($s['last_result'], 0, 120)] + $s, $sites), 0, 10),
        ]);
    }

    private function classify(string $result): string
    {
        $res = strtolower(trim($result));
        foreach (self::PENDING as $p) if (str_starts_with($res, $p)) return 'pending';
        foreach (self::OK as $p) if (str_starts_with($res, $p)) return 'ok';
        return 'failed';
    }
}
