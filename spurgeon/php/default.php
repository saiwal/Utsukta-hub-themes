<?php
/**
 *   * Name: default
 *   * Description: Hubzilla default 3-column layout
 *   * Version: 1
 *   * Author: Mario Vavti
 *   * Maintainer: Mario Vavti
 *   * ContentRegion: aside, left_aside_wrapper
 *   * ContentRegion: content, region_2
 *   * ContentRegion: right_aside, right_aside_wrapper
 */
?>
<!DOCTYPE html >
<html prefix="og: http://ogp.me/ns#" <?php if(x($page,'color_mode')) echo $page['color_mode'] ?>>
<head>
  <title><?php if(x($page,'title')) echo $page['title'] ?></title>
  <script>var baseurl="<?php echo z_root() ?>";</script>
  <?php if(x($page,'htmlhead')) echo $page['htmlhead'] ?>
</head>
<body <?php if($page['direction']) echo 'dir="rtl"' ?> >
	<?php if(x($page,'banner')) echo $page['banner']; ?>
	<header><?php if(x($page,'header')) echo $page['header']; ?></header>
	<?php if(x($page,'nav')) echo $page['nav']; ?>
	<main>
		<div class="content">
			<div class="columns">
				<aside id="region_1" class="d-none d-lg-block"><div class="aside_spacer_top_left"></div><div class="aside_spacer_left"><div id="left_aside_wrapper" class="aside_wrapper"><?php if(x($page,'aside')) echo $page['aside']; ?></div></div></aside>
				<section id="region_2"><?php if(x($page,'content')) echo $page['content']; ?>
					<div id="page-footer"></div>
					<div id="pause"></div>
				</section>
				<aside id="region_3" class="d-none d-xl-block"><div class="aside_spacer_top_right"></div><div class="aside_spacer_right"><div id="right_aside_wrapper" class="aside_wrapper"><?php if(x($page,'right_aside')) echo $page['right_aside']; ?></div></div></aside>
			</div>
		</div>
	</main>
	<footer><?php if(x($page,'footer')) echo $page['footer']; ?></footer>
</body>
</html>
