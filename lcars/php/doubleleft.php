<?php

/**
 *   * Name: oubleleft
 *   * Description: LCARS doubleleft layout
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
  <?php if (x($page, 'htmlhead')) echo $page['htmlhead'] ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
	<meta name="format-detection" content="telephone=no">
	<meta name="format-detection" content="date=no">
</head>
<body  data-bs-theme="dark">
	<audio id="audio1" src="/view/theme/lcars/assets/beep1.mp3" preload="auto"></audio>
	<audio id="audio2" src="/view/theme/lcars/assets/beep2.mp3" preload="auto"></audio>
	<audio id="audio3" src="/view/theme/lcars/assets/beep3.mp3" preload="auto"></audio>
	<audio id="audio4" src="/view/theme/lcars/assets/beep4.mp3" preload="auto"></audio>
	<section class="wrap-standard" id="column-3">
  <header><?php if (x($page, 'header')) echo $page['header']; ?></header>
		<?php if (x($page, 'nav')) echo $page['nav']; ?>
				<main>

					<!-- Start your content here. -->
					<?php if(!empty($page['banner'])) echo $page['banner']; ?>
					<section id="content" class="s-content s-content--page">
						<div class="container-fluid">
							<div class="row">
								<div class="col-lg-8 col-md-12">
									<div id="region_2">
										<?php if (x($page, 'content')) echo $page['content']; ?>
									</div>
								</div>
								<div class="col-lg-4" id="sidebar-column">
									<div id="region_3">
										<div id="left_aside_wrapper">
											<?php if (x($page, 'right_aside')) echo $page['right_aside']; ?>
										</div>
										<div id="right_aside_wrapper">
											<?php if (x($page, 'aside')) echo $page['aside']; ?>
										</div>
									</div>
								</div>
							</div>
						</div>
					</section>
					<!-- End content area. -->

				</main>
				<footer>
					<!-- The following attribution must not be removed: -->
					LCARS Inspired Website Template by <a href="https://www.thelcars.com">www.TheLCARS.com</a> adapted for Hubzilla.				 		 
						<div class="lcars-text-bar the-end">
							<span>Live Long and Prosper</span>
						</div>
				</footer> 
			</div>
		</div>
	</section>	
	<script type="text/javascript" src="/view/theme/lcars/assets/lcars.js"></script>
	<div class="headtrim"> </div>
	<div class="baseboard"> </div>
</body>
</html>

