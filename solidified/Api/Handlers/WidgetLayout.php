<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

/**
 * POST /api/widget-layout
 *
 * Body: { layout: { version: 1, modules: { <moduleId>: { <slot>: [widgetIds] } } } }
 *       { layout: null } clears the saved layout (revert everything to defaults).
 *
 * Stored as JSON in pconfig cat "spa", key "widget_layout" — delivered to the
 * SPA at boot via GET /api/pconfig. Widget/module ids are opaque strings here;
 * the SPA validates them against its widget registry when rendering.
 */
class WidgetLayout
{
    private const SLOTS = ['right', 'leftBottom', 'mainTop', 'rightVisitor'];
    private const MAX_MODULES = 32;
    private const MAX_WIDGETS_PER_SLOT = 16;
    private const ID_PATTERN = '/^[a-zA-Z0-9._-]{1,64}$/';

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
            foreach ($slots as $slot => $ids) {
                if (!in_array($slot, self::SLOTS, true) || !is_array($ids)
                    || count($ids) > self::MAX_WIDGETS_PER_SLOT) {
                    Response::error(400, 'Invalid layout');
                }
                foreach ($ids as $id) {
                    if (!is_string($id) || !preg_match(self::ID_PATTERN, $id)) {
                        Response::error(400, 'Invalid layout');
                    }
                }
                // An empty array is meaningful: "user removed every widget here"
                $clean_slots[$slot] = array_values(array_unique($ids));
            }
            if ($clean_slots) {
                $clean_modules[$module_id] = $clean_slots;
            }
        }

        if (!$clean_modules) return null;

        return ['version' => 1, 'modules' => $clean_modules];
    }
}
