<?php

/**
 *   * Name: default
 *   * Description: keepitsimples default layout
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


<body id="top">

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
		<?php if (x($page, 'header')) echo $page['header']; ?>
	</header>
	<!-- Header
    ================================================== -->
	<header class="s-header">
		<div class="row position-relative">
			<div class="s-header__content column">
				<h1 class="s-header__logotext">
					<a href="/" title="">
					<?php echo $page['header_text'] ?? $page['banner'] ?>
					</a>
				</h1>
				<p class="s-header__tagline">
					<?php echo $page['subtitle'] ?>
				</p>
			</div>

		</div> <!-- end row -->

		<?php if (x($page, 'nav')) echo $page['nav']; ?>

	</header> <!-- Header End -->
	<!-- Content
    ================================================== -->
	<div class="s-content">

		<div class="row">

			<main id="main" class="s-content__main large-8 column">

				<section class="page-content">

					<?php if (x($page, 'content')) echo $page['content']; ?>

				</section> <!-- end page -->

			</main> <!-- end main -->


			<div id="sidebar" class="s-content__sidebar large-4 column">
				<div class="offcanvas-lg offcanvas-end" tabindex="-1" id="offcanvasResponsive"
					aria-labelledby="offcanvasResponsiveLabel">
					<div class="offcanvas-header mt-2">
						<button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#offcanvasResponsive"
							aria-label="Close"></button>
					</div>
					<div class="offcanvas-body">
						<div class="container">
							<div id="region_1" class="pe-0 w-100">
								<div id="left_aside_wrapper">
									<!-- Search needs to be implemented as a widget to support language translations -->
									<div class="widget widget--search" id="search-autocomplete-results">
										<form action="search" method="get" role="search">
											<input type="text" value="Search site @name, !forum, #tag, ?docs, content" onblur="if(this.value == '') { this.value = 'Search site @name, !forum, #tag, ?docs, content'; }"
											 onfocus="if (this.value == 'Search site @name, !forum, #tag, ?docs, content') { this.value = ''; }" class="text-search" id="nav-search-text" name="search">
											<input type="submit" class="submit-search">
										</form>
									</div>
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

			</div> <!-- end row -->

		</div> <!-- end content-wrap -->

	</div>
	<!-- Footer
    ================================================== -->
	<footer class="s-footer">

		<div class="row s-footer__top">
			<div class="column">
				<ul class="s-footer__social">
					<li><a href="#0"><i class="fab fa-facebook-f" aria-hidden="true"></i></a></li>
					<li><a href="#0"><i class="fab fa-twitter" aria-hidden="true"></i></a></li>
					<li><a href="#0"><i class="fab fa-youtube" aria-hidden="true"></i></a></li>
					<li><a href="#0"><i class="fab fa-vimeo-v" aria-hidden="true"></i></a></li>
					<li><a href="#0"><i class="fab fa-instagram" aria-hidden="true"></i></a></li>
					<li><a href="#0"><i class="fab fa-linkedin" aria-hidden="true"></i></a></li>
					<li><a href="#0"><i class="fab fa-skype" aria-hidden="true"></i></a></li>
				</ul>
			</div>
		</div> <!-- end footer__top -->

		<div class="row s-footer__bottom">

			<?php if (x($page, 'footer')) echo $page['footer']; ?>

			<div class="ss-copyright">
<span><a href="/siteinfo">Siteinfo</a></span> 
				<span>Design by <a href="https://www.styleshout.com/">StyleShout</a> Adapted for Hubzilla</span>
			</div>

		</div> <!-- end footer__bottom -->

		<div class="ss-offcanvas">
			<a class="nav-link d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive"
				aria-controls="offcanvasResponsive">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path
						d="M2 7.81125V16.1913C2 17.6813 2.36 18.9212 3.05 19.8713C3.34 20.2913 3.71 20.6612 4.13 20.9513C4.95 21.5513 5.99 21.9012 7.22 21.9812V2.03125C3.94 2.24125 2 4.37125 2 7.81125Z"
						fill="#292D32" />
					<path
						d="M20.9507 4.13C20.6607 3.71 20.2907 3.34 19.8707 3.05C18.9207 2.36 17.6807 2 16.1907 2H8.7207V22H16.1907C19.8307 22 22.0007 19.83 22.0007 16.19V7.81C22.0007 6.32 21.6407 5.08 20.9507 4.13ZM15.5007 14.03C15.7907 14.32 15.7907 14.8 15.5007 15.09C15.3507 15.24 15.1607 15.31 14.9707 15.31C14.7807 15.31 14.5907 15.24 14.4407 15.09L11.8807 12.53C11.5907 12.24 11.5907 11.76 11.8807 11.47L14.4407 8.91C14.7307 8.62 15.2107 8.62 15.5007 8.91C15.7907 9.2 15.7907 9.68 15.5007 9.97L13.4807 12L15.5007 14.03Z"
						fill="#292D32" />
				</svg>
			</a>
		</div>

		<div class="ss-go-top">
			<a class="smoothscroll" title="Back to Top" href="#top">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
					<path d="M12 0l8 9h-6v15h-4v-15h-6z" />
				</svg>
			</a>
		</div> <!-- end ss-go-top -->

	</footer> <!-- end Footer-->

	<script src="/view/theme/keepitsimple/js/maintr.js"></script>
</body>

</html>
