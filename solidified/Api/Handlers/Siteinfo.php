<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

class Siteinfo
{
    private function listServiceClassNames(): array
    {
        \Zotlabs\Lib\Config::Load('service_class');
        $names = array_keys(\App::$config['service_class'] ?? []);
        return array_values(array_diff($names, ['config_loaded', 'upgrade_link']));
    }

    private function buildServiceClasses(): array
    {
        require_once('include/text.php'); // engr_units_to_bytes()

        $default = \Zotlabs\Lib\Config::Get('system', 'default_service_class');
        $classes = [];

        foreach ($this->listServiceClassNames() as $name) {
            $props = \Zotlabs\Lib\Config::Get('service_class', $name);
            if (!is_array($props)) $props = [];

            $photo  = (int) engr_units_to_bytes($props['photo_upload_limit']  ?? 0);
            $attach = (int) engr_units_to_bytes($props['attach_upload_limit'] ?? 0);
            $unlimited_storage = ($photo <= 0 || $attach <= 0);

            $chan  = (int) ($props['total_channels']   ?? 0);
            $items = (int) ($props['total_items']      ?? 0);
            $ident = (int) ($props['total_identities'] ?? 0);
            $price = (float) ($props['price'] ?? 0);

            $classes[] = [
                'name'                => $name,
                'is_default'          => ($name === $default),
                'photo_upload_limit'  => $photo  > 0 ? $photo  : null,
                'attach_upload_limit' => $attach > 0 ? $attach : null,
                'storage_total'       => $unlimited_storage ? null : $photo + $attach,
                'total_channels'      => $chan  > 0 ? $chan  : null,
                'total_items'         => $items > 0 ? $items : null,
                'total_identities'    => $ident > 0 ? $ident : null,
                'price'               => $price > 0 ? $price : null,
            ];
        }

        usort($classes, function ($a, $b) {
            $av = $a['storage_total'] ?? PHP_INT_MAX;
            $bv = $b['storage_total'] ?? PHP_INT_MAX;
            return $av <=> $bv ?: strcmp($a['name'], $b['name']);
        });

        return $classes;
    }

    public function get(): void
    {
        $federated = [];
        call_hooks('federated_transports', $federated);

        $themes_raw = \Zotlabs\Lib\Config::Get('system', 'allowed_themes') ?? '';
        $themes = array_values(array_filter(array_map('trim', explode(',', $themes_raw))));

        $blocked = \Zotlabs\Lib\Config::Get('system', 'blacklisted_sites') ?? [];
        if (is_string($blocked)) {
            $blocked = array_values(array_filter(array_map('trim', explode(',', $blocked))));
        }

        $hidden_version = \Zotlabs\Lib\Config::Get('system', 'hidden_version_siteinfo');

        header('Content-Type: application/json; charset=utf-8');
        Response::send([
            'site_name' => \Zotlabs\Lib\System::get_site_name(),
            'site_about' => \Zotlabs\Lib\Config::Get('system', 'siteinfo'),
            'admin_about' => \Zotlabs\Lib\Config::Get('system', 'admininfo'),
            'version' => $hidden_version ? null : \Zotlabs\Lib\System::get_project_version(),
            'project_link' => \Zotlabs\Lib\System::get_project_link(),
            'project_src' => \Zotlabs\Lib\System::get_project_srclink(),
            'addons' => array_values(\App::$plugins ?? []),
            'themes' => $themes,
            'blocked_sites' => $blocked,
            'federated' => array_values(is_array($federated) ? $federated : []),
            'registration' => (int) \Zotlabs\Lib\Config::Get('system', 'register_policy'),
            'service_classes' => $this->buildServiceClasses(),
        ]);
        exit;
    }
}
