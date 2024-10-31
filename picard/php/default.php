<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- Usually browsers proactively perform domain name resolution on links that the user may choose to follow. We disable DNS prefetching here -->
    <meta http-equiv="x-dns-prefetch-control" content="off" />
    <meta http-equiv="cache-control" content="max-age=60,private" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php if(x($page,'title')) echo $page['title'] ?></title>
    <script>var baseurl="<?php echo z_root() ?>";</script>
    <?php if(x($page,'htmlhead')) echo $page['htmlhead'] ?>
    <!-- Theme styles -->
    <meta name="theme-color" content="#53596C" />
    <style>
      html {
        background-color: #000;
      }
    </style>
  </head>
  <body class="lcars-picard hold-transition sidebar-mini logged-in">
    <noscript>
      <!-- JS Warning -->
      <div>
        <input type="checkbox" id="js-hide" />
        <div class="js-warn" id="js-warn-exit">
          <h1>JavaScript Is Disabled</h1>
          <p>JavaScript is required for the site to function.</p>
          <p>
            To learn how to enable JavaScript click
            <a
              href="https://www.enable-javascript.com/"
              rel="noopener"
              target="_blank"
              >here</a
            >
          </p>
          <label for="js-hide">Close</label>
        </div>
      </div>
      <!-- /JS Warning -->
    </noscript>
    <div id="token" hidden>0FqRhnvxXSaVCLHlFLFGxbtpxnxMwv1Aa4izR8p+XrI=</div>
    <!-- Send token to JS -->
    <div id="enableTimer" hidden></div>
    <main class="wrapper">

      <?php if(x($page,'nav')) echo $page['nav']; ?>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Main content -->
        <section class="content">
          <div class="columns">
            <aside id="region_1" class="d-none d-lg-block">
              <div class="aside_spacer_top_left"></div>
              <div class="aside_spacer_left">
                <div id="left_aside_wrapper" class="aside_wrapper">
                  <?php if(x($page,'aside')) echo $page['aside']; ?>
                </div>
              </div>
            </aside>
            <section id="region_2">
              <?php if(x($page,'content')) echo $page['content']; ?>
              <div id="page-footer"></div>
              <div id="pause"></div>
            </section>
            <aside id="region_3" class="d-none d-xl-block">
              <div class="aside_spacer_top_right"></div>
              <div class="aside_spacer_right">
                <div id="right_aside_wrapper" class="aside_wrapper">
                  <?php if(x($page,'right_aside')) echo $page['right_aside']; ?>
                </div>
              </div>
            </aside>
          </div>
        </section>
      </div>
      <!-- /.content-wrapper -->

      <footer class="main-footer">
        <div class="row row-centered text-center">
          <div class="col-xs-12 col-sm-6">
            <strong
              ><a
                href="https://pi-hole.net/donate/"
                rel="noopener"
                target="_blank"
                ><i class="fa fa-heart text-red"></i> Donate</a
              ></strong
            >
            if you found this useful.
          </div>
        </div>

        <div class="row row-centered text-center version-info">
          <div class="col-xs-12 col-sm-12 col-md-10">
            <ul class="list-inline">
              <li>
                <strong>Docker Tag</strong>
                <a
                  href="https://github.com/pi-hole/docker-pi-hole/releases/2024.07.0"
                  rel="noopener"
                  target="_blank"
                  >2024.07.0</a
                >
              </li>
              <li>
                <strong>Pi-hole</strong>
                <a
                  href="https://github.com/pi-hole/pi-hole/releases/v5.18.3"
                  rel="noopener"
                  target="_blank"
                  >v5.18.3</a
                >
              </li>
              <li>
                <strong>FTL</strong>
                <a
                  href="https://github.com/pi-hole/FTL/releases/v5.25.2"
                  rel="noopener"
                  target="_blank"
                  >v5.25.2</a
                >
              </li>
              <li>
                <strong>Web Interface</strong>
                <a
                  href="https://github.com/pi-hole/AdminLTE/releases/v5.21"
                  rel="noopener"
                  target="_blank"
                  >v5.21</a
                >
              </li>
            </ul>

            <p style="margin: 15px 0 0"></p>
          </div>
        </div>
      </footer>
    </main>
    <!-- ./wrapper -->
  </body>
</html>
