<?php
namespace Theme\Solidified\Api\Handlers;

use Zotlabs\Lib\Config;
use Zotlabs\Lib\Libzotdir;
use Theme\Solidified\Api\Response;

class Pubsites
{
    public function get(): void
    {
        $dirmode = intval(Config::Get('system', 'directory_mode'));
        $url = '';

        if ($dirmode == DIRECTORY_MODE_PRIMARY || $dirmode == DIRECTORY_MODE_STANDALONE) {
            $url = z_root() . '/dirsearch';
        }

        if (!$url) {
            $directory = Libzotdir::find_upstream_directory($dirmode);
            $url = $directory['url'] . '/dirsearch';
        }

        $url .= '/sites';

        $ret = z_fetch_url($url);

        if (!$ret['success']) {
            Response::send(['error' => 'Failed to fetch sites'], 500);
        }

        $j = json_decode($ret['body'], true);
        $sites = [];

        if ($j && !empty($j['sites'])) {
            foreach ($j['sites'] as $jj) {
                $projectname = explode(' ', $jj['project']);
                if (!\Zotlabs\Lib\System::compatible_project($projectname[0]))
                    continue;

                if (strpos($jj['version'], ' ')) {
                    $x = explode(' ', $jj['version']);
                    if ($x[1]) $jj['version'] = $x[1];
                }

                $host = strtolower(substr($jj['url'], strpos($jj['url'], '://') + 3));

                $sites[] = [
                    'url'            => $jj['url'],
                    'urltext'        => str_replace('https://', '', $jj['url']),
                    'host'           => $host,
                    'sellpage'       => $jj['sellpage'] ?? null,
                    'access'         => $jj['access'] ?? '',
                    'register'       => $jj['register'] ?? '',
                    'project'        => ucwords($jj['project']),
                    'version'        => $jj['version'] ?? '',
                    'location'       => $jj['location'] ?? '',
                    'rating_enabled' => (bool) Config::Get('system', 'rating_enabled'),
                    'can_rate'       => (bool) local_channel(),
                ];
            }
        }

        Response::send(['sites' => $sites]);
    }
}
