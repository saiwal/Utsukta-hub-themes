<?php

/**
 * * Name: default
 *   * Description: Solidified default
 *   * Version: 0.0-alpha
 *   * Author: Saiwal
 *   * Maintainer: Saiwal
 *   * ContentRegion: aside, right_aside_wrapper
 *   * ContentRegion: right_aside, left_aside_wrapper
 *   * ContentRegion: content, region_2
 */
?>

<!DOCTYPE html>
<html>
<head>
	<title><?php if (x($page, 'title')) echo $page['title'] ?></title>
	<script>
    var baseurl = "<?php echo z_root() ?>";
  </script>
  <link rel="stylesheet" href="/view/theme/solidified/assets/app.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
	<meta name="format-detection" content="telephone=no">
	<meta name="format-detection" content="date=no">
	<link rel="icon" href="/view/theme/solidified/assets/favicon.ico">
	<link rel="manifest" href="/api/manifest" />
	<meta name="theme-color" content="#1e293b" />
	<meta name="mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
	<meta name="apple-mobile-web-app-title" content="Solidified" />
	<link rel="apple-touch-icon" href="/view/theme/solidified/assets/icon-192.png" />
</head>

<body>

<div id="root"></div>

<script type="module" src="/view/theme/solidified/assets/app.js"></script>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/api/sw', { scope: '/' })
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
        })
        .catch(function (err) {
          console.warn('[PWA] SW registration failed:', err);
        });
    });
  }
</script>
</body>
</html>
