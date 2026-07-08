<?php
namespace Theme\Solidified\Api;

use Theme\Solidified\Api\Handlers;

class Router
{
    private static array $map = [
        'pconfig' => Handlers\Pconfig::class,
        'sw' => Handlers\Sw::class,
        'manifest' => Handlers\Manifest::class,
        'csrf' => Handlers\Csrf::class,
        'siteinfo' => Handlers\Siteinfo::class,
        'settings' => Handlers\Settings::class,
        'userconfig' => Handlers\UserConfig::class,
        'help' => Handlers\Help::class,
        'display' => Handlers\Display::class,
        'chat' => Handlers\Chat::class,
        'cal' => Handlers\Cal::class,
        'pubsites' => Handlers\Pubsites::class,
        'manage' => Handlers\Manage::class,
        'stream-widgets' => Handlers\StreamWidgets::class,
        'widget-layout' => Handlers\WidgetLayout::class,
        'drafts' => Handlers\Drafts::class,
        'item' => Handlers\Item::class,
        'item-source' => Handlers\Itemsrc::class,
        'privacy-groups' => Handlers\PrivacyGroups::class,
        'articles' => Handlers\Articles::class,
        'network' => Handlers\Network::class,
        'channel' => Handlers\Channel::class,
        'profile' => Handlers\Profile::class,
        'profiles' => Handlers\Profiles::class,
        'photos' => Handlers\Photos::class,
        'files'  => Handlers\Files::class,
        'pubstream' => Handlers\Pubstream::class,
        'webpages' => Handlers\Webpages::class,
        'notes' => Handlers\Notes::class,
        'wiki' => Handlers\Wiki::class,
        'nav' => Handlers\Nav::class,
        'admin' => Handlers\Admin::class,
        'login'    => Handlers\Login::class,
        'rmagic'   => Handlers\Rmagic::class,
        'logout'   => Handlers\Logout::class,
        'register' => Handlers\Register::class,
        'regate'   => Handlers\Regate::class,
        'search' => Handlers\Search::class,
        'saved-searches' => Handlers\SavedSearches::class,
        'bookmarks'      => Handlers\Bookmarks::class,
        'avatar'         => Handlers\Avatar::class,
        'folders'        => Handlers\Folders::class,
        'xchan'          => Handlers\Xchan::class,
        'connections'    => Handlers\Connections::class,
        'directory'      => Handlers\Directory::class,
        'cart'           => Handlers\Cart::class,
        'rss-feed'       => Handlers\Rss::class,
        'weather'        => Handlers\Weather::class,
        'announcements'  => Handlers\Announcements::class,
    ];

    public static function dispatch(string $method): void
    {
        // URL: /api/settings       → argv: [0]=api  [1]=settings
        // URL: /api/connections/42 → argv: [0]=api  [1]=connections  [2]=42
        // Decode percent-encoded segments (needed for mid values in /api/item/:mid/*)
        \App::$argv = array_map('urldecode', \App::$argv);

        $resource = \App::$argv[1] ?? null;

        if (!$resource) {
            Response::error(400, 'No resource specified');
        }

        if (!isset(self::$map[$resource])) {
            Response::error(404, "Unknown endpoint: {$resource}");
        }

        $handlerClass = self::$map[$resource];
        $handler = new $handlerClass();

        if (!method_exists($handler, $method)) {
            Response::error(405, 'Method not allowed');
        }

        $handler->$method();
    }
}
