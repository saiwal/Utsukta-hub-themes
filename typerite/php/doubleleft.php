<?php
/**
 *   * Name: doubleleft
 *   * Description: typerites default layout
 *   * Version: 1.0
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


<body class="ss-bg-white">

	<!-- preloader
    ================================================== -->
	<div id="preloader">
		<div id="loader" class="dots-fade">
			<div></div>
			<div></div>
			<div></div>
		</div>
	</div>

	<header>
		<?php if(x($page,'header')) echo $page['header']; ?>
	</header>
	<div id="top" class="s-wrap site-wrapper">
		<?php if (x($page, 'nav')) echo $page['nav']; ?>
		<!-- site content
        ================================================== -->
		<div class="s-content content">
			<main class="row s-styles">
				<section id="styles" class="column large-full">
					<div class="row section-intro add-bottom">
						<div class="col-xxl-8 col-xl-12">
							<div id="region_2">
								<?php if (x($page, 'content')) echo $page['content']; ?>
							</div>
						</div>
						<div class="col-xxl-4">
							<div class="offcanvas-xxl offcanvas-end" tabindex="-1" id="offcanvasResponsive"
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
				</section>

			</main>

		</div> <!-- end s-content -->


		<!-- footer
        ================================================== -->
		<footer class="s-footer footer">
			<div class="row">
				<div class="column large-full footer__content">
					<div class="footer__copyright">
						<span>Design by <a href="https://www.styleshout.com/">StyleShout</a> Adapted for Hubzilla</span>
					</div>
				</div>
			</div>
			<div class="ss-offcanvas">
				<a class="nav-link d-xxl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive"
					aria-controls="offcanvasResponsive">
					<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
						x="0px" y="0px" width="36px" height="36px" viewBox="-4 -4 36 36" enable-background="new 4 -4 16 32"
						xml:space="preserve">
						<path
							d="M2 7.81125V16.1913C2 17.6813 2.36 18.9212 3.05 19.8713C3.34 20.2913 3.71 20.6612 4.13 20.9513C4.95 21.5513 5.99 21.9012 7.22 21.9812V2.03125C3.94 2.24125 2 4.37125 2 7.81125Z" fill="#FFFFFF" />
						<path
							d="M20.9507 4.13C20.6607 3.71 20.2907 3.34 19.8707 3.05C18.9207 2.36 17.6807 2 16.1907 2H8.7207V22H16.1907C19.8307 22 22.0007 19.83 22.0007 16.19V7.81C22.0007 6.32 21.6407 5.08 20.9507 4.13ZM15.5007 14.03C15.7907 14.32 15.7907 14.8 15.5007 15.09C15.3507 15.24 15.1607 15.31 14.9707 15.31C14.7807 15.31 14.5907 15.24 14.4407 15.09L11.8807 12.53C11.5907 12.24 11.5907 11.76 11.8807 11.47L14.4407 8.91C14.7307 8.62 15.2107 8.62 15.5007 8.91C15.7907 9.2 15.7907 9.68 15.5007 9.97L13.4807 12L15.5007 14.03Z" fill="#FFFFFF" />
					</svg></a>
			</div>

			<div class="go-top">
				<a class="smoothscroll" title="Back to Top" href="#top">
					</a>
			</div>
		</footer>

	</div> <!-- end s-wrap -->

	<script src="/view/theme/typerite/js/plugins.js"></script>
	<script src="/view/theme/typerite/js/scripts.js"></script>
</body>

</html>
