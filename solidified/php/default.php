<?php

/**
 *   * Name: default
 *   * Description: Solidified default
 *   * Version: 0.0
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
</head>

<body>

<div id="root"></div>

<script type="module" src="/view/theme/solidified/assets/app.js"></script>

</body>
</html>
