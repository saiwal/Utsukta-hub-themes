<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

class Siteinfo
{
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
        ]);
        exit;
    }
}
