<?php

/**
 * * Name: Default
 *   * Description: Spurgeons default layout with left column
 *   * Version: 0.0-beta
 *   * Author: Saiwal
 *   * Maintainer: Saiwal
 *   * ContentRegion: aside, right_aside_wrapper
 *   * ContentRegion: right_aside, left_aside_wrapper
 *   * ContentRegion: content, region_2
 */
?>
<!DOCTYPE html>
<html lang="en" class="no-js">

<head>

	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>
		<?php if (x($page, 'title')) echo $page['title'] ?>
	</title>
	<script>
		var baseurl = "<?php echo z_root() ?>";
	</script>
	<script>
		document.documentElement.classList.remove('no-js');
		document.documentElement.classList.add('js');
	</script>


	<?php if (x($page, 'htmlhead')) echo $page['htmlhead'] ?>
</head>


<body id="top">

	<div id="preloader">
		<div id="loader" class="dots-fade">
			<div></div>
			<div></div>
			<div></div>
		</div>
	</div>


	<div id="page" class="s-pagewrap">
		<header>
			<?php if (x($page, 'header')) echo $page['header']; ?>
		</header>

		<header id="masthead" class="s-header">
			<?php if (x($page, 'nav')) echo $page['nav']; ?>
		</header>

		<?php if(!empty($page['banner'])) echo $page['banner']; ?>
		<section id="content" class="s-content s-content--page">
			<main class="bricks pt-0">
				<div class="masonry">
					<div class="row entry-wrap">
						<div class="column lg-8 md-12">
							<div id="region_2">
								<?php if (x($page, 'content')) echo $page['content']; ?>
							</div>
						</div>
						<div class="column lg-4">
							<div class="offcanvas-xl offcanvas-end" tabindex="-1" id="offcanvasResponsive"
								aria-labelledby="offcanvasResponsiveLabel">
								<div class="offcanvas-header mt-2">
									<button type="button" class="btn-close" data-bs-dismiss="offcanvas"
										data-bs-target="#offcanvasResponsive" aria-label="Close"></button>
								</div>
								<div class="offcanvas-body px-0">
									<div class="container">
										<div id="region_1" class="pe-0 w-100">
											<div id="left_aside_wrapper">
												<?php if (x($page, 'right_aside')) echo $page['right_aside']; ?>
											</div>
										</div>
										<div id="region_3" class="pe-0 w-100">
											<div id="right_aside_wrapper">
												<?php if (x($page, 'aside')) echo $page['aside']; ?>
											</div>
										</div>
									</div>
								</div>

							</div> <!-- end sidebar -->
						</div>
					</div>
				</div>
			</main>
		</section>


		<footer id="colophon" class="s-footer">
			<div class="row s-footer__main">
				<?php if (x($page, 'footer')) echo $page['footer']; ?>
			</div> <!-- end s-footer__main -->

			<div class="row s-footer__bottom">

				<div class="column lg-12 tab-12">
					<div class="ss-copyright">
						<span>Design by <a href="https://www.styleshout.com/">StyleShout</a>, Adapted for Hubzilla</span>
					</div>
				</div>

			</div> <!-- end s-footer__bottom -->
			<div class="ss-offcanvas">
				<a class="nav-link d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive"
					aria-controls="offcanvasResponsive">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path
							d="M2 7.81125V16.1913C2 17.6813 2.36 18.9212 3.05 19.8713C3.34 20.2913 3.71 20.6612 4.13 20.9513C4.95 21.5513 5.99 21.9012 7.22 21.9812V2.03125C3.94 2.24125 2 4.37125 2 7.81125Z" />
						<path
							d="M20.9507 4.13C20.6607 3.71 20.2907 3.34 19.8707 3.05C18.9207 2.36 17.6807 2 16.1907 2H8.7207V22H16.1907C19.8307 22 22.0007 19.83 22.0007 16.19V7.81C22.0007 6.32 21.6407 5.08 20.9507 4.13ZM15.5007 14.03C15.7907 14.32 15.7907 14.8 15.5007 15.09C15.3507 15.24 15.1607 15.31 14.9707 15.31C14.7807 15.31 14.5907 15.24 14.4407 15.09L11.8807 12.53C11.5907 12.24 11.5907 11.76 11.8807 11.47L14.4407 8.91C14.7307 8.62 15.2107 8.62 15.5007 8.91C15.7907 9.2 15.7907 9.68 15.5007 9.97L13.4807 12L15.5007 14.03Z" />
					</svg>
				</a>
			</div>

			<div class="ss-go-top">
				<a class="smoothscroll" title="Back to Top" href="#top">
					<svg width="24" height="24" fill="none" viewBox="0 0 24 24">
						<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
							d="M17.25 10.25L12 4.75L6.75 10.25" />
						<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
							d="M12 19.25V5.75" />
					</svg>
				</a>
			</div> <!-- end ss-go-top -->

		</footer><!-- end s-footer -->
	</div>
	<script src="/view/theme/spurgeon/js/plugins.js"></script>
	<script src="/view/theme/spurgeon/js/scripts.js"></script>
	<script>
		document.addEventListener("DOMContentLoaded", function () {
			const sidebar = document.getElementById("sidebar-column");
			const toggle = document.getElementById("sidebar-toggle");

			if (!sidebar || !toggle) return;

			toggle.addEventListener("click", function (e) {
				e.preventDefault(); // prevent page jump
				sidebar.classList.toggle("is-open");
				document.body.classList.toggle("no-scroll");
			});

			// Optional: close sidebar when clicking outside
			document.addEventListener("click", function (e) {
				if (
					sidebar.classList.contains("is-open") &&
					!sidebar.contains(e.target) &&
					!toggle.contains(e.target)
				) {
					sidebar.classList.remove("is-open");
					document.body.classList.remove("no-scroll");
				}
			});
		});
	</script>
</body>

</html>
