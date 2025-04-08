<?php
use \Zotlabs\Lib\Apps;
use \Zotlabs\Lib\Chatroom;
use \Zotlabs\Lib\Config;

require_once('include/security.php');
require_once('include/menu.php');

function widget_footer_nav($args) {

  /**
	 *
   * Build Top navbar
	 *
	 */

  App::$page['footer_nav'] = App::$page['footer_nav'] ?? ''; App::$page['htmlhead'] =

      $is_owner = (((local_channel()) && ((App::$profile_uid ==
        local_channel()) || (App::$profile_uid == 0))) ? true : false);
      $observer = App::get_observer(); $chans = []; $channel = [];

      if (local_channel()) { $channel = App::get_channel(); $prof = q("select
        id from profile where uid = %d and is_default = 1",
        intval($channel['channel_id']));

      if (empty($_SESSION['delegate']) && feature_enabled(local_channel(),
        'nav_channel_select')) { $chans = q("select channel_name, channel_id
        from channel where channel_account_id = %d and channel_removed = 0
        order by channel_name ", intval(get_account_id())); }

      $sitelocation = (($is_owner) ? '' : App::$profile['reddress']); } else {
      $sitelocation = ((isset(App::$profile['reddress'])) ?
        App::$profile['reddress'] : '@' . App::get_hostname()); }

      require_once('include/conversation.php');

      $nav_apps     = []; $navbar_apps  = []; $channel_apps = [];

      if (isset(App::$profile['channel_address'])) { $channel_apps[] =
        channel_apps($is_owner, App::$profile['channel_address']); }

      // nav links: array of array('href', 'text', 'extra css classes',
      // 'title')
      $nav = [];

      if (can_view_public_stream()) $nav['pubs'] = true;


        $syslist = Apps::app_order(local_channel(), $syslist,
          'nav_featured_app');

        if ($pinned_list) { foreach ($pinned_list as $app) { if
          (isset(App::$nav_sel['name']) && App::$nav_sel['name'] ==
          $app['name']) $app['active'] = true;

        if ($is_owner) { $navbar_apps[] = Apps::app_render($app, 'navbar'); }
        elseif (!$is_owner && strpos($app['requires'], 'local_channel') ===
          false) { $navbar_apps[] = Apps::app_render($app, 'navbar'); } } }

          if ($syslist) { foreach ($syslist as $app) { if
            (isset(App::$nav_sel['name']) && App::$nav_sel['name'] ==
            $app['name']) { $app['active'] = true; } if ($is_owner) {
            $nav_apps[] = Apps::app_render($app, 'nav'); } elseif
              (!isset($app['requires']) || (isset($app['requires']) &&
              strpos($app['requires'], 'local_channel') === false)) {
              $nav_apps[] = Apps::app_render($app, 'nav'); } } }

                $c   = theme_include('navbar_' . purify_filename($template) .
                '.css'); $tpl = get_markup_template('navbar_' .
                purify_filename($template) . '.tpl');

              if ($c && $tpl) { head_add_css('navbar_' . $template . '.css'); }

              if (!$tpl) { $tpl = get_markup_template('footer_nav.tpl'); }

              App::$page['footer_nav'] .= replace_macros($tpl, [ '$baseurl' =>
                z_root(), '$color_mode'         => App::$page['color_mode'] ??
                '', '$navbar_color_mode'  => App::$page['navbar_color_mode'] ??
                '', '$theme_switch_icon'  => $theme_switch_icon, '$fulldocs' =>
                t('Help'), '$sitelocation'       => $sitelocation, '$nav' =>
                $x['nav'], '$banner'             => $banner,
                '$emptynotifications' => t('Loading'), '$userinfo'           =>
                $x['usermenu'], '$localuser'          => local_channel(),
                '$is_owner'           => $is_owner, '$sel'                =>
                App::$nav_sel, '$help'               => t('@name, #tag, ?doc,
                  content'), '$pleasewait'         => t('Please wait...'),
                  '$nav_apps'           => $nav_apps, '$navbar_apps'        =>
                  $navbar_apps, '$channel_menu'       =>
                  get_pconfig(App::$profile_uid, 'system', 'channel_menu',
                    Config::Get('system', 'channel_menu')), '$channel_thumb' =>
                    ((App::$profile) ? App::$profile['thumb'] : ''),
                    '$channel_apps'       => $channel_apps, '$addapps' =>
                    t('Apps'), '$channelapps'        => t('Channel Apps'),
                    '$sysapps'            => t('System Apps'), '$pinned_apps'
                    => t('Pinned Apps'), '$featured_apps'      => t('Featured
                    Apps'), '$url'                => (($url) ? $url : z_root()
                    . '/' . App::$cmd), '$settings_url'       => $settings_url,
                    '$name'               => ((!$is_owner &&
                    isset(App::$profile['fullname'])) ?
                    App::$profile['fullname'] : ''), '$thumb'              =>
                    ((!$is_owner && isset(App::$profile['thumb'])) ?
                    App::$profile['thumb'] : ''), '$form_security_token' =>
                    get_form_security_token('pconfig') ]);

              if (x($_SESSION, 'reload_avatar') && $observer) {
                // The avatar has been changed on the server but the browser
                // doesn't know that, force the browser to reload the image
                // from the server instead of its cache.
                $tpl = get_markup_template('force_image_reload.tpl');

                App::$page['footer_nav'] .= replace_macros($tpl, [ '$imgUrl' =>
                  $observer['xchan_photo_m'] ]);
                unset($_SESSION['reload_avatar']); }

              return App::$page['footer_nav']; 
              /*call_hooks('page_header', App::$page['topnav']); */

}
