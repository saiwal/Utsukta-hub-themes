<?php
namespace Theme\Solidified\Api\Handlers;

use App;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

/**
 * GET    /api/saved-searches        — list all saved searches for the current user
 * POST   /api/saved-searches        — create a new saved search
 * DELETE /api/saved-searches/:tid   — delete a saved search by its term id
 *
 * Uses the core `term` table with ttype = TERM_SAVEDSEARCH (6).
 * The search label is stored in `term`; the full filter params (JSON) go in `url`.
 */
class SavedSearches
{
    public function get(): void
    {
        $uid = Auth::requireLocalGet();

        $r = q(
            "SELECT tid, term, url FROM term WHERE uid = %d AND ttype = %d ORDER BY tid DESC",
            intval($uid),
            intval(TERM_SAVEDSEARCH)
        );

        $items = array_map(function($row) {
            $params = null;
            if (!empty($row['url'])) {
                $decoded = json_decode($row['url'], true);
                if (is_array($decoded) && !empty($decoded)) {
                    $params = $decoded;
                }
            }
            // Fall back for searches saved by classic Hubzilla (url is empty)
            if ($params === null) {
                $params = ['search' => $row['term']];
            }
            return [
                'id'     => intval($row['tid']),
                'label'  => $row['term'],
                'params' => $params,
            ];
        }, $r ?: []);

        Response::send($items);
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();

        $label  = trim(Auth::$parsedBody['label']  ?? '');
        $params = Auth::$parsedBody['params'] ?? [];

        if (!$label) {
            Response::error(400, 'Label is required');
        }

        if (!is_array($params)) {
            Response::error(400, 'Params must be an object');
        }

        // Prevent duplicate labels for this user
        $existing = q(
            "SELECT tid FROM term WHERE uid = %d AND ttype = %d AND term = '%s' LIMIT 1",
            intval($uid),
            intval(TERM_SAVEDSEARCH),
            dbesc($label)
        );

        if ($existing) {
            Response::error(409, 'A saved search with this name already exists');
        }

        $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);

        q(
            "INSERT INTO term (uid, ttype, term, url) VALUES (%d, %d, '%s', '%s')",
            intval($uid),
            intval(TERM_SAVEDSEARCH),
            dbesc($label),
            dbesc($paramsJson)
        );

        $inserted = q(
            "SELECT tid FROM term WHERE uid = %d AND ttype = %d AND term = '%s' ORDER BY tid DESC LIMIT 1",
            intval($uid),
            intval(TERM_SAVEDSEARCH),
            dbesc($label)
        );

        if (!$inserted) {
            Response::error(500, 'Failed to save search');
        }

        Response::send([
            'id'     => intval($inserted[0]['tid']),
            'label'  => $label,
            'params' => $params,
        ], [], 201);
    }

    public function delete(): void
    {
        $uid = Auth::requireLocalJson();
        $tid = intval(App::$argv[2] ?? 0);

        if (!$tid) {
            Response::error(400, 'tid required');
        }

        q(
            "DELETE FROM term WHERE tid = %d AND uid = %d AND ttype = %d",
            intval($tid),
            intval($uid),
            intval(TERM_SAVEDSEARCH)
        );

        Response::send(['deleted' => true]);
    }
}
