<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

/**
 * GET  /api/widget-templates → the owner's saved layout templates.
 * POST /api/widget-templates → create/rename/delete a template, or replace
 *      one template's widget list for a slot.
 *
 * A template is a reusable, named widget arrangement that a webpage (or,
 * later, any other module) can be assigned to instead of getting its own
 * one-off per-item override — storage grows with the number of distinct
 * templates an owner maintains, not with the number of pages using them.
 *
 * Stored as JSON in pconfig cat "spa", key "widget_templates":
 *   { "version": 1, "templates": { "tpl_xxxxxx": { "name": "...", "slots": { "right": [entries] } } } }
 *
 * Each template's "slots" shape and entry validation are identical to one
 * module entry of "widget_layout" (see WidgetLayout.php) — a slot entry is
 * either a plain widget id (singleton widgets) or an instance object:
 *   { "id": "cart.item_card", "key": "cart.item_card#a1b2c3", "config": { ... } }
 */
class WidgetTemplates
{
    private const SLOTS = ['right', 'leftBottom', 'mainTop', 'rightVisitor', 'header', 'footer'];
    private const MAX_TEMPLATES = 32;
    private const MAX_WIDGETS_PER_SLOT = 32;
    private const ID_PATTERN = '/^[a-zA-Z0-9._-]{1,64}$/';
    private const KEY_PATTERN = '/^[a-zA-Z0-9._-]{1,64}(#[a-zA-Z0-9_-]{1,16})?$/';
    private const CONFIG_MAX_BYTES = 2048;
    private const NAME_MAX_LEN = 60;

    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $doc = self::load($uid);
        $doc['usage'] = self::usageCounts($uid);
        Response::send($doc);
    }

    // How many of the owner's webpages currently point at each template id —
    // lets the management screen show "Unused" so redundant templates can be
    // found without guesswork. Only needed for the GET (management screen)
    // response; usage doesn't change from create/rename/delete/save_slots.
    private static function usageCounts(int $uid): array
    {
        $rows = q(
            "SELECT iconfig.v AS template_id, COUNT(*) AS cnt
             FROM iconfig
             JOIN item ON item.id = iconfig.iid
             WHERE item.uid = %d
               AND iconfig.cat = 'spa'
               AND iconfig.k = 'layout_template'
               AND item.item_type = %d
               AND item.item_deleted = 0
             GROUP BY iconfig.v",
            intval($uid),
            intval(ITEM_TYPE_WEBPAGE)
        );

        $usage = [];
        foreach (($rows ?: []) as $row) {
            $usage[$row['template_id']] = intval($row['cnt']);
        }
        return $usage;
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $body = Auth::$parsedBody;
        $action = $body['action'] ?? '';

        switch ($action) {
            case 'create':
                $this->create($uid, $body);
                return;
            case 'rename':
                $this->rename($uid, $body);
                return;
            case 'delete':
                $this->delete($uid, $body);
                return;
            case 'save_slots':
                $this->saveSlots($uid, $body);
                return;
        }

        Response::error(400, 'Unknown action');
    }

    private function create(int $uid, array $body): void
    {
        $name = self::cleanName($body['name'] ?? '');
        if ($name === null) {
            Response::error(400, 'Invalid name');
        }

        $doc = self::load($uid);
        if (count($doc['templates']) >= self::MAX_TEMPLATES) {
            Response::error(400, 'Too many templates');
        }

        $id = 'tpl_' . substr(bin2hex(random_bytes(6)), 0, 12);
        // Plain empty array here (not stdClass) — PHP json_encode renders this
        // as "[]"; the client already tolerates that for an empty map (same
        // convention as widget_layout's "modules").
        $doc['templates'][$id] = ['name' => $name, 'slots' => []];
        self::save($uid, $doc);

        Response::send(['id' => $id, 'templates' => $doc['templates']]);
    }

    private function rename(int $uid, array $body): void
    {
        $id = (string) ($body['id'] ?? '');
        $name = self::cleanName($body['name'] ?? '');
        if ($name === null || !preg_match(self::ID_PATTERN, $id)) {
            Response::error(400, 'Invalid request');
        }

        $doc = self::load($uid);
        if (!isset($doc['templates'][$id])) {
            Response::error(404, 'Template not found');
        }
        $doc['templates'][$id]['name'] = $name;
        self::save($uid, $doc);

        Response::send(['templates' => $doc['templates']]);
    }

    private function delete(int $uid, array $body): void
    {
        $id = (string) ($body['id'] ?? '');
        if (!preg_match(self::ID_PATTERN, $id)) {
            Response::error(400, 'Invalid request');
        }

        $doc = self::load($uid);
        unset($doc['templates'][$id]);
        self::save($uid, $doc);

        Response::send(['templates' => $doc['templates']]);
    }

    private function saveSlots(int $uid, array $body): void
    {
        $id = (string) ($body['id'] ?? '');
        $slot = (string) ($body['slot'] ?? '');
        $entries = $body['entries'] ?? null;

        if (!preg_match(self::ID_PATTERN, $id) || !in_array($slot, self::SLOTS, true)) {
            Response::error(400, 'Invalid request');
        }

        $doc = self::load($uid);
        if (!isset($doc['templates'][$id])) {
            Response::error(404, 'Template not found');
        }

        $clean_entries = self::validate_entries($entries);

        // An empty array is meaningful ("removed every widget"); omit the key
        // entirely only when there never were any entries for this slot.
        if ($clean_entries || isset($doc['templates'][$id]['slots'][$slot])) {
            $doc['templates'][$id]['slots'][$slot] = $clean_entries;
        }
        self::save($uid, $doc);

        Response::send(['templates' => $doc['templates']]);
    }

    /** @return array{version:1, templates: array} */
    private static function load(int $uid): array
    {
        $raw = get_pconfig($uid, 'spa', 'widget_templates', '');
        $decoded = $raw ? json_decode($raw, true) : null;
        if (!is_array($decoded) || ($decoded['version'] ?? null) !== 1 || !is_array($decoded['templates'] ?? null)) {
            return ['version' => 1, 'templates' => []];
        }
        return ['version' => 1, 'templates' => $decoded['templates']];
    }

    private static function save(int $uid, array $doc): void
    {
        if (!$doc['templates']) {
            del_pconfig($uid, 'spa', 'widget_templates');
            return;
        }
        set_pconfig($uid, 'spa', 'widget_templates', json_encode($doc));
    }

    private static function cleanName($name): ?string
    {
        if (!is_string($name)) return null;
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > self::NAME_MAX_LEN) return null;
        return $name;
    }

    /** Validates a slot's entry list — same rules as WidgetLayout::validate_entry. */
    private static function validate_entries($entries): array
    {
        if (!is_array($entries) || count($entries) > self::MAX_WIDGETS_PER_SLOT) {
            Response::error(400, 'Invalid entries');
        }
        $clean = [];
        $seen = [];
        foreach ($entries as $entry) {
            $c = self::validate_entry($entry);
            $key = is_string($c) ? $c : $c['key'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $clean[] = $c;
        }
        return $clean;
    }

    /** @return string|array */
    private static function validate_entry($entry)
    {
        if (is_string($entry)) {
            if (!preg_match(self::ID_PATTERN, $entry)) {
                Response::error(400, 'Invalid entry');
            }
            return $entry;
        }

        if (!is_array($entry)
            || !is_string($entry['id'] ?? null) || !preg_match(self::ID_PATTERN, $entry['id'])
            || !is_string($entry['key'] ?? null) || !preg_match(self::KEY_PATTERN, $entry['key'])) {
            Response::error(400, 'Invalid entry');
        }

        $clean = ['id' => $entry['id'], 'key' => $entry['key']];

        if (array_key_exists('config', $entry) && $entry['config'] !== null) {
            if (!is_array($entry['config'])) {
                Response::error(400, 'Invalid entry');
            }
            $encoded = json_encode($entry['config']);
            if ($encoded === false || strlen($encoded) > self::CONFIG_MAX_BYTES) {
                Response::error(400, 'Invalid entry');
            }
            if ($entry['config']) {
                $clean['config'] = $entry['config'];
            }
        }

        return $clean;
    }
}
