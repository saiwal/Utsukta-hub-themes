<?php
/**
 * Utsukta\SpaCore\Api\Concerns\FilesByRules
 *
 * Auto-files incoming posts into inbox folders, Thunderbird-style. A rule is
 * `{ id, name, folder, expr }` where `expr` is the message-filter DSL string
 * the client's filter-dsl.ts already compiles for the connection and channel
 * filter boxes — so there is exactly one compiler and it lives on the client.
 * Matching is core's own `Zotlabs\Lib\MessageFilter` with an empty exclude
 * list, which makes `evaluate()` a plain "did this expression match" predicate.
 *
 * Filing writes the same `TERM_FILE` term `Item.php::saveToFolder()` writes, so
 * an auto-filed post is indistinguishable from a hand-filed one and the folder
 * list, counts, badges and drag-and-drop need no changes.
 *
 * Runs at read time on the inbox's unfiltered feed. A cursor
 * (`pconfig spa/rules_cursor`) holds the newest `created` already considered,
 * so each post is evaluated once and rules apply to new mail only. That is the
 * semantic a mail client is expected to have, and it costs one pconfig read and
 * at most one write per page rather than a per-item verdict row.
 */

namespace Utsukta\SpaCore\Api\Concerns;

use Zotlabs\Lib\MessageFilter;

// No file-scope requires: every caller (HqMessages, Settings) runs inside a
// booted Hubzilla, and keeping this file loadable on its own is what lets
// FilesByRules.test.php run without a database.

trait FilesByRules
{
    public const RULES_MAX       = 16;
    public const RULE_EXPR_MAX   = 512;
    public const RULE_NAME_MAX   = 64;

    /** Decoded, validated rule list. Invalid entries are dropped, not fatal. */
    public static function inboxRules(int $uid): array
    {
        $raw = get_pconfig($uid, 'spa', 'inbox_rules', '');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? self::validateRules($decoded) : [];
    }

    /**
     * Shared by the reader above and the settings POST, so a rule that would be
     * skipped at read time can't be saved in the first place.
     */
    public static function validateRules(array $rules): array
    {
        $out = [];
        foreach ($rules as $r) {
            if (!is_array($r)) {
                continue;
            }
            $folder = trim((string) ($r['folder'] ?? ''));
            $expr   = trim((string) ($r['expr']   ?? ''));
            // A rule with no expression would match nothing; one with no folder
            // has nowhere to put it. Either way it is not a rule.
            if ($folder === '' || $expr === '') {
                continue;
            }
            if (mb_strlen($folder) > self::RULE_NAME_MAX || mb_strlen($expr) > self::RULE_EXPR_MAX) {
                continue;
            }
            $out[] = [
                'id'      => substr(notags((string) ($r['id'] ?? '')), 0, 32) ?: random_string(8),
                'name'    => substr(notags((string) ($r['name'] ?? '')), 0, self::RULE_NAME_MAX),
                'folder'  => notags($folder),
                'expr'    => $expr,
                'enabled' => !isset($r['enabled']) || (bool) $r['enabled'],
            ];
            if (count($out) >= self::RULES_MAX) {
                break;
            }
        }
        return $out;
    }

    /**
     * Files everything in $items that a rule matches and is newer than the
     * cursor. $items must already have been through fetch_post_tags() — the
     * `#tag` / `@mention` / `$category` rule forms read `$item['term']`.
     *
     * Matched folders are pushed onto the in-memory `term` list too, so the
     * response that triggered the filing already shows the folder chip.
     */
    protected function applyInboxRules(array &$items, int $uid): void
    {
        $rules = array_values(array_filter(self::inboxRules($uid), fn($r) => $r['enabled']));
        if (!$rules || !$items) {
            return;
        }

        $cursor = (string) get_pconfig($uid, 'spa', 'rules_cursor', '');
        $newest = '';
        foreach ($items as $item) {
            if (($item['created'] ?? '') > $newest) {
                $newest = $item['created'];
            }
        }

        // First run: adopt the current high-water mark and file nothing. Rules
        // are forward-looking, and silently refiling a whole mailbox on the
        // first page load after saving one would be a nasty surprise.
        if ($cursor === '') {
            if ($newest !== '') {
                set_pconfig($uid, 'spa', 'rules_cursor', $newest);
            }
            return;
        }

        foreach ($items as &$item) {
            if (($item['created'] ?? '') <= $cursor) {
                continue;
            }
            // Same preparation post_is_importable() does — without it a lang=
            // rule detects the language of raw bbcode.
            $plaintext = prepare_text($item['body'] ?? '', $item['mimetype'] ?? 'text/bbcode');
            $plaintext = html2plain($plaintext);

            // MessageFilter's ?field tester is flat-key only — $item['author']
            // is an array, so ?author.xchan_name would silently read ''. The
            // sender fields read these helper keys instead, set on a copy so
            // nothing downstream sees them. Keys must match filter-dsl.ts.
            $probe = $item + [
                'filter_author_name' => (string) ($item['author']['xchan_name'] ?? ''),
                'filter_author_addr' => (string) ($item['author']['xchan_addr'] ?? ''),
            ];

            $existing = [];
            foreach (($item['term'] ?? []) as $term) {
                if (intval($term['ttype']) === TERM_FILE) {
                    $existing[] = $term['term'];
                }
            }

            foreach ($rules as $rule) {
                if (in_array($rule['folder'], $existing, true)) {
                    continue;
                }
                if (!(new MessageFilter($probe, html_entity_decode($rule['expr']), '', ['plaintext' => $plaintext]))->evaluate()) {
                    continue;
                }
                $this->fileItem($uid, $item, $rule['folder']);
                $existing[] = $rule['folder'];
                $item['term'][] = [
                    'uid' => $uid, 'oid' => intval($item['id']), 'otype' => TERM_OBJ_POST,
                    'ttype' => TERM_FILE, 'term' => $rule['folder'], 'url' => '',
                ];
            }
        }
        unset($item);

        if ($newest > $cursor) {
            set_pconfig($uid, 'spa', 'rules_cursor', $newest);
        }
    }

    /** The filing half of Item.php::saveToFolder(), minus the HTTP envelope. */
    private function fileItem(int $uid, array $item, string $folder): void
    {
        store_item_tag($uid, intval($item['id']), TERM_OBJ_POST, TERM_FILE, $folder, '');
        // item_retained rides on the thread root, as the manual path does.
        q("UPDATE item SET item_retained = 1, changed = '%s' WHERE id = %d AND uid = %d",
            dbesc(datetime_convert()),
            intval($item['parent']) ?: intval($item['id']),
            intval($uid)
        );
    }
}
