<?php

/**
 * * Name: doubleleft
 *   * Description: Solidified doubleleft, same as default
 *   * Version: 0.1-beta
 *   * Author: Saiwal
 *   * Maintainer: Saiwal
 *   * ContentRegion: aside, right_aside_wrapper
 *   * ContentRegion: right_aside, left_aside_wrapper
 *   * ContentRegion: content, region_2
 */

require_once __DIR__ . '/manifest.php';
$solidified_assets = solidified_assets();
$solidified_favicon = get_config('system', 'sitelogo_favicon') ?: '/view/theme/solidified/assets/favicon.ico';
$solidified_touch_icon = get_config('system', 'sitelogo_192') ?: '/view/theme/solidified/assets/apple-touch-icon-180x180.png';
?>

<!DOCTYPE html>
<html>
<head>
	<title><?php if (x($page, 'title')) echo $page['title'] ?></title>
	<script>
    var baseurl = "<?php echo z_root() ?>";
  </script>
  <?php foreach ($solidified_assets['css'] as $solidified_css): ?>
  <link rel="stylesheet" href="<?php echo $solidified_css ?>">
  <?php endforeach; ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
	<meta name="format-detection" content="telephone=no">
	<meta name="format-detection" content="date=no">
	<link rel="icon" href="<?php echo $solidified_favicon ?>">
	<link rel="manifest" href="/api/manifest" />
	<meta name="theme-color" content="#1e293b" />
	<meta name="mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
	<meta name="apple-mobile-web-app-title" content="Solidified" />
	<link rel="apple-touch-icon" href="<?php echo $solidified_touch_icon ?>" />
</head>

<body>

<div id="root"></div>

<script type="module" src="<?php echo $solidified_assets['js'] ?>"></script>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/api/sw', { scope: '/', updateViaCache: 'none' })
        .then(function (reg) {
          console.log('[PWA] SW registered, scope:', reg.scope);
          reg.addEventListener('updatefound', function () {
            var nw = reg.installing;
            nw.addEventListener('statechange', function () {
              if (nw.state === 'installed' && navigator.serviceWorker.controller) {
                window.dispatchEvent(new CustomEvent('pwa-update-available'));
              }
            });
          });
          // SPAs rarely hard-navigate, so poll for a new SW hourly and
          // whenever the (installed) app returns to the foreground.
          var checkForUpdate = function () { reg.update().catch(function () {}); };
          setInterval(checkForUpdate, 60 * 60 * 1000);
          document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') checkForUpdate();
          });
        })
        .catch(function (err) {
          console.warn('[PWA] SW registration failed:', err);
        });
    });
  }
</script>
</body>
</html>
