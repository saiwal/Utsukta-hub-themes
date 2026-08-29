<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Text;
use App;

/**
 * Channel Sources handler
 *
 * A "source" nominates a connection whose public posts get re-owned by your
 * channel and redistributed to *your* connections, optionally filtered by a
 * MessageFilter pattern and stamped with categories.
 *
 * GET    /spa/sources        → list
 * POST   /spa/sources        → create (no id) or update (id in body)
 * DELETE /spa/sources/:id    → delete
 *
 * Register in Router.php $map:
 *   'sources' => Handlers\Sources::class,
 *
 * Storage matches core's Zotlabs/Module/Sources.php exactly (same escaping,
 * same columns) so a source stays editable from the classic /sources page.
 *
 * Note `resend` is not a `source` column: it is abconfig system/rself, and it
 * rewrites the imported post's author to you (include/items.php).
 */
class Sources
{
    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $this->requireApp($uid);

        $rows = q("SELECT source.*, xchan.xchan_name, xchan.xchan_photo_s, xchan.xchan_addr
                     FROM source LEFT JOIN xchan ON src_xchan = xchan_hash
                    WHERE src_channel_id = %d
                    ORDER BY src_id ASC",
            intval($uid)
        );

        Response::send(array_map(fn($r) => $this->format($uid, $r), $rows ?: []));
    }

    public function post(): void
    {
        $uid  = Auth::requireLocalJson();
        $this->requireApp($uid);

        $data    = Auth::$parsedBody;
        $channel = App::get_channel();

        $id     = intval($data['id'] ?? 0);
        $xchan  = trim((string) ($data['xchan'] ?? ''));
        // Same escaping core applies, so the classic UI reads back what we wrote.
        $words  = Text::escape_tags((string) ($data['words'] ?? ''));
        $tags   = Text::escape_tags((string) ($data['tags'] ?? ''));
        $resend = intval(!empty($data['resend']));

        if (!$xchan)
            Response::error(400, 'No channel selected');

        // check_item_source() bails on this at delivery time and only logs it,
        // so the source would silently never fire. Refuse it up front.
        if ($xchan === $channel['channel_hash'])
            Response::error(400, 'Cannot source your own channel');

        if ($id) {
            $existing = q("SELECT src_id FROM source WHERE src_id = %d AND src_channel_id = %d LIMIT 1",
                intval($id), intval($uid));
            if (!$existing)
                Response::error(404, 'Source not found');
        } else {
            // check_item_source() picks a matching row with LIMIT 1, so a duplicate
            // makes it nondeterministic which pattern/tags actually apply.
            $dupe = q("SELECT src_id FROM source WHERE src_channel_id = %d AND src_xchan = '%s' LIMIT 1",
                intval($uid), dbesc($xchan));
            if ($dupe)
                Response::error(409, 'A source for this channel already exists');
        }

        set_abconfig($uid, $xchan, 'system', 'rself', $resend);

        if ($id) {
            q("UPDATE source SET src_xchan = '%s', src_patt = '%s', src_tag = '%s'
                WHERE src_channel_id = %d AND src_id = %d",
                dbesc($xchan), dbesc($words), dbesc($tags),
                intval($uid), intval($id)
            );
        } else {
            q("INSERT INTO source ( src_channel_id, src_channel_xchan, src_xchan, src_patt, src_tag )
                VALUES ( %d, '%s', '%s', '%s', '%s' )",
                intval($uid), dbesc($channel['channel_hash']),
                dbesc($xchan), dbesc($words), dbesc($tags)
            );
            $new = q("SELECT src_id FROM source WHERE src_channel_id = %d AND src_xchan = '%s'
                       ORDER BY src_id DESC LIMIT 1",
                intval($uid), dbesc($xchan));
            if (!$new)
                Response::error(500, 'Failed to create source');
            $id = intval($new[0]['src_id']);
        }

        $row = q("SELECT source.*, xchan.xchan_name, xchan.xchan_photo_s, xchan.xchan_addr
                    FROM source LEFT JOIN xchan ON src_xchan = xchan_hash
                   WHERE src_id = %d AND src_channel_id = %d LIMIT 1",
            intval($id), intval($uid)
        );
        if (!$row)
            Response::error(404, 'Source not found');

        Response::send($this->format($uid, $row[0]));
    }

    public function delete(): void
    {
        $uid = Auth::requireLocalJson();
        $this->requireApp($uid);

        $id = intval(App::$argv[2] ?? 0);
        if (!$id)
            Response::error(400, 'Missing source id');

        // Leave the rself abconfig alone — it is shared per-connection state.
        q("DELETE FROM source WHERE src_id = %d AND src_channel_id = %d",
            intval($id), intval($uid));

        Response::send(['deleted' => true, 'id' => $id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireApp(int $uid): void
    {
        // Must be the exact .apd name: system_app_installed() hashes it.
        if (!Apps::system_app_installed($uid, 'Channel Sources'))
            Response::error(403, 'Channel Sources app is not installed');
    }

    private function format(int $uid, array $r): array
    {
        $xchan    = $r['src_xchan'];
        $wildcard = ($xchan === '*');

        return [
            'id'     => intval($r['src_id']),
            'xchan'  => $xchan,
            // null for the wildcard row — the client supplies its own label.
            'name'   => $wildcard ? null : Response::decodeEntities($r['xchan_name'] ?? ''),
            'photo'  => $wildcard ? null : ($r['xchan_photo_s'] ?? null),
            'addr'   => $wildcard ? null : ($r['xchan_addr'] ?? null),
            'words'  => Response::decodeEntities($r['src_patt']),
            'tags'   => Response::decodeEntities($r['src_tag']),
            'resend' => (bool) intval(get_abconfig($uid, $xchan, 'system', 'rself')),
            // Without their_perms/republish the source silently imports nothing.
            // The wildcard row is evaluated per-item at delivery time instead.
            'republish_granted' => $wildcard
                ? true
                : (bool) intval(get_abconfig($uid, $xchan, 'their_perms', 'republish')),
        ];
    }
}
