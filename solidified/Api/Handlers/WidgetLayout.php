<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

/**
 * POST /api/widget-layout
 *
 * Body: { layout: { version: 1, modules: { <moduleId>: { <slot>: [entries] } } } }
 *       { layout: null } clears the saved layout (revert everything to defaults).
 *
 * A slot entry is either a plain widget id (singleton widgets) or an instance
 * object for multi-instance widgets:
 *   { "id": "cart.item_card", "key": "cart.item_card#a1b2c3", "config": { ... } }
 * `key` must be unique within the slot; `config` is an opaque settings object
 * handed back to the widget, capped at CONFIG_MAX_BYTES of JSON.
 *
 * Stored as JSON in pconfig cat "spa", key "widget_layout" — delivered to the
 * SPA at boot via GET /api/pconfig. Widget/module ids are opaque strings here;
 * the SPA validates them against its widget registry when rendering.
 */
class WidgetLayout
{
    private const SLOTS = ['right', 'leftBottom', 'mainTop', 'rightVisitor'];
    private const MAX_MODULES = 32;
    private const MAX_WIDGETS_PER_SLOT = 32;
    private const ID_PATTERN = '/^[a-zA-Z0-9._-]{1,64}$/';
    private const KEY_PATTERN = '/^[a-zA-Z0-9._-]{1,64}(#[a-zA-Z0-9_-]{1,16})?$/';
    private const CONFIG_MAX_BYTES = 2048;

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $body = Auth::$parsedBody;

        if (!is_array($body) || !array_key_exists('layout', $body)) {
            Response::error(400, 'Missing layout');
        }

        $clean = self::validate($body['layout']);

        if ($clean === null) {
            del_pconfig($uid, 'spa', 'widget_layout');
            Response::send(['status' => 'ok']);
        }

        set_pconfig($uid, 'spa', 'widget_layout', json_encode($clean));
        Response::send(['status' => 'ok']);
    }

    /**
     * Returns the sanitised layout, null for "clear" (explicit null or an
     * effectively empty layout), and errors out on malformed input.
     */
    private static function validate($layout): ?array
    {
        if ($layout === null) return null;

        if (!is_array($layout) || ($layout['version'] ?? null) !== 1 || !isset($layout['modules'])) {
            Response::error(400, 'Invalid layout');
        }

        $modules = $layout['modules'];
        if (!is_array($modules) || count($modules) > self::MAX_MODULES) {
            Response::error(400, 'Invalid layout');
        }

        $clean_modules = [];
        foreach ($modules as $module_id => $slots) {
            if (!is_string($module_id) || !preg_match(self::ID_PATTERN, $module_id) || !is_array($slots)) {
                Response::error(400, 'Invalid layout');
            }
            $clean_slots = [];
            foreach ($slots as $slot => $entries) {
                if (!in_array($slot, self::SLOTS, true) || !is_array($entries)
                    || count($entries) > self::MAX_WIDGETS_PER_SLOT) {
                    Response::error(400, 'Invalid layout');
                }
                $clean_entries = [];
                $seen_keys = [];
                foreach ($entries as $entry) {
                    $clean = self::validate_entry($entry);
                    $key = is_string($clean) ? $clean : $clean['key'];
                    if (isset($seen_keys[$key])) {
                        continue;
                    }
                    $seen_keys[$key] = true;
                    $clean_entries[] = $clean;
                }
                // An empty array is meaningful: "user removed every widget here"
                $clean_slots[$slot] = $clean_entries;
            }
            if ($clean_slots) {
                $clean_modules[$module_id] = $clean_slots;
            }
        }

        if (!$clean_modules) return null;

        return ['version' => 1, 'modules' => $clean_modules];
    }

    /**
     * Validates one slot entry (plain id string or instance object) and
     * returns it with only the recognised fields kept. Errors out otherwise.
     *
     * @return string|array
     */
    private static function validate_entry($entry)
    {
        if (is_string($entry)) {
            if (!preg_match(self::ID_PATTERN, $entry)) {
                Response::error(400, 'Invalid layout');
            }
            return $entry;
        }

        if (!is_array($entry)
            || !is_string($entry['id'] ?? null) || !preg_match(self::ID_PATTERN, $entry['id'])
            || !is_string($entry['key'] ?? null) || !preg_match(self::KEY_PATTERN, $entry['key'])) {
            Response::error(400, 'Invalid layout');
        }

        $clean = ['id' => $entry['id'], 'key' => $entry['key']];

        if (array_key_exists('config', $entry) && $entry['config'] !== null) {
            if (!is_array($entry['config'])) {
                Response::error(400, 'Invalid layout');
            }
            $encoded = json_encode($entry['config']);
            if ($encoded === false || strlen($encoded) > self::CONFIG_MAX_BYTES) {
                Response::error(400, 'Invalid layout');
            }
            if ($entry['config']) {
                $clean['config'] = $entry['config'];
            }
        }

        return $clean;
    }
}
