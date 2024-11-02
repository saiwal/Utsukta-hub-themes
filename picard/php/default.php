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
    <div class="wrapper">

  <?php if(x($page,'nav')) echo $page['nav']; ?>
      <main>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper row">
        <!-- Main content -->
        <section class="content">
          <div class="row">
            <div class="col-lg-3 col-sm-6">
              <!-- small box -->
              <div class="small-box bg-aqua no-user-select" id="total_queries" title="only A + AAAA queries">
                <div class="inner">
                  <p>Total queries</p>
                  <h3 class="statistic">
                    <span id="dns_queries_today">---</span>
                  </h3>
                </div>
                <div class="icon">
                  <svg class="svg-inline--fa fa-globe-americas fa-w-16" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="globe-americas" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 496 512" data-fa-i2svg=""><path fill="currentColor" d="M248 8C111.03 8 0 119.03 0 256s111.03 248 248 248 248-111.03 248-248S384.97 8 248 8zm82.29 357.6c-3.9 3.88-7.99 7.95-11.31 11.28-2.99 3-5.1 6.7-6.17 10.71-1.51 5.66-2.73 11.38-4.77 16.87l-17.39 46.85c-13.76 3-28 4.69-42.65 4.69v-27.38c1.69-12.62-7.64-36.26-22.63-51.25-6-6-9.37-14.14-9.37-22.63v-32.01c0-11.64-6.27-22.34-16.46-27.97-14.37-7.95-34.81-19.06-48.81-26.11-11.48-5.78-22.1-13.14-31.65-21.75l-.8-.72a114.792 114.792 0 0 1-18.06-20.74c-9.38-13.77-24.66-36.42-34.59-51.14 20.47-45.5 57.36-82.04 103.2-101.89l24.01 12.01C203.48 89.74 216 82.01 216 70.11v-11.3c7.99-1.29 16.12-2.11 24.39-2.42l28.3 28.3c6.25 6.25 6.25 16.38 0 22.63L264 112l-10.34 10.34c-3.12 3.12-3.12 8.19 0 11.31l4.69 4.69c3.12 3.12 3.12 8.19 0 11.31l-8 8a8.008 8.008 0 0 1-5.66 2.34h-8.99c-2.08 0-4.08.81-5.58 2.27l-9.92 9.65a8.008 8.008 0 0 0-1.58 9.31l15.59 31.19c2.66 5.32-1.21 11.58-7.15 11.58h-5.64c-1.93 0-3.79-.7-5.24-1.96l-9.28-8.06a16.017 16.017 0 0 0-15.55-3.1l-31.17 10.39a11.95 11.95 0 0 0-8.17 11.34c0 4.53 2.56 8.66 6.61 10.69l11.08 5.54c9.41 4.71 19.79 7.16 30.31 7.16s22.59 27.29 32 32h66.75c8.49 0 16.62 3.37 22.63 9.37l13.69 13.69a30.503 30.503 0 0 1 8.93 21.57 46.536 46.536 0 0 1-13.72 32.98zM417 274.25c-5.79-1.45-10.84-5-14.15-9.97l-17.98-26.97a23.97 23.97 0 0 1 0-26.62l19.59-29.38c2.32-3.47 5.5-6.29 9.24-8.15l12.98-6.49C440.2 193.59 448 223.87 448 256c0 8.67-.74 17.16-1.82 25.54L417 274.25z"></path></svg><!-- <i class="fas fa-globe-americas"></i> Font Awesome fontawesome.com -->
                </div>
                <a href="network.php" class="small-box-footer" title="">
                  <span id="unique_clients">-</span> active clients
                  <svg class="svg-inline--fa fa-arrow-circle-right fa-w-16" aria-hidden="true" focusable="false" data-prefix="fa" data-icon="arrow-circle-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 8c137 0 248 111 248 248S393 504 256 504 8 393 8 256 119 8 256 8zm-28.9 143.6l75.5 72.4H120c-13.3 0-24 10.7-24 24v16c0 13.3 10.7 24 24 24h182.6l-75.5 72.4c-9.7 9.3-9.9 24.8-.4 34.3l11 10.9c9.4 9.4 24.6 9.4 33.9 0L404.3 273c9.4-9.4 9.4-24.6 0-33.9L271.6 106.3c-9.4-9.4-24.6-9.4-33.9 0l-11 10.9c-9.5 9.6-9.3 25.1.4 34.4z"></path></svg><!-- <i class="fa fa-arrow-circle-right"></i> Font Awesome fontawesome.com -->
                </a>
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-sm-6">
              <!-- small box -->
              <div class="small-box bg-red no-user-select">
                <div class="inner">
                  <p>Queries Blocked</p>
                  <h3 class="statistic">
                    <span id="queries_blocked_today">---</span>
                  </h3>
                </div>
                <div class="icon">
                  <svg class="svg-inline--fa fa-hand-paper fa-w-14" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="hand-paper" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" data-fa-i2svg=""><path fill="currentColor" d="M408.781 128.007C386.356 127.578 368 146.36 368 168.79V256h-8V79.79c0-22.43-18.356-41.212-40.781-40.783C297.488 39.423 280 57.169 280 79v177h-8V40.79C272 18.36 253.644-.422 231.219.007 209.488.423 192 18.169 192 40v216h-8V80.79c0-22.43-18.356-41.212-40.781-40.783C121.488 40.423 104 58.169 104 80v235.992l-31.648-43.519c-12.993-17.866-38.009-21.817-55.877-8.823-17.865 12.994-21.815 38.01-8.822 55.877l125.601 172.705A48 48 0 0 0 172.073 512h197.59c22.274 0 41.622-15.324 46.724-37.006l26.508-112.66a192.011 192.011 0 0 0 5.104-43.975V168c.001-21.831-17.487-39.577-39.218-39.993z"></path></svg><!-- <i class="fas fa-hand-paper"></i> Font Awesome fontawesome.com -->
                </div>
                <a href="queries.php?forwarddest=blocked" class="small-box-footer" title="">
                  List blocked queries <svg class="svg-inline--fa fa-arrow-circle-right fa-w-16" aria-hidden="true" focusable="false" data-prefix="fa" data-icon="arrow-circle-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 8c137 0 248 111 248 248S393 504 256 504 8 393 8 256 119 8 256 8zm-28.9 143.6l75.5 72.4H120c-13.3 0-24 10.7-24 24v16c0 13.3 10.7 24 24 24h182.6l-75.5 72.4c-9.7 9.3-9.9 24.8-.4 34.3l11 10.9c9.4 9.4 24.6 9.4 33.9 0L404.3 273c9.4-9.4 9.4-24.6 0-33.9L271.6 106.3c-9.4-9.4-24.6-9.4-33.9 0l-11 10.9c-9.5 9.6-9.3 25.1.4 34.4z"></path></svg><!-- <i class="fa fa-arrow-circle-right"></i> Font Awesome fontawesome.com -->
                </a>
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-sm-6">
              <!-- small box -->
              <div class="small-box bg-yellow no-user-select">
                <div class="inner">
                  <p>Percentage Blocked</p>
                  <h3 class="statistic">
                    <span id="percentage_blocked_today">---</span>
                  </h3>
                </div>
                <div class="icon">
                  <svg class="svg-inline--fa fa-chart-pie fa-w-17" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chart-pie" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 544 512" data-fa-i2svg=""><path fill="currentColor" d="M527.79 288H290.5l158.03 158.03c6.04 6.04 15.98 6.53 22.19.68 38.7-36.46 65.32-85.61 73.13-140.86 1.34-9.46-6.51-17.85-16.06-17.85zm-15.83-64.8C503.72 103.74 408.26 8.28 288.8.04 279.68-.59 272 7.1 272 16.24V240h223.77c9.14 0 16.82-7.68 16.19-16.8zM224 288V50.71c0-9.55-8.39-17.4-17.84-16.06C86.99 51.49-4.1 155.6.14 280.37 4.5 408.51 114.83 513.59 243.03 511.98c50.4-.63 96.97-16.87 135.26-44.03 7.9-5.6 8.42-17.23 1.57-24.08L224 288z"></path></svg><!-- <i class="fas fa-chart-pie"></i> Font Awesome fontawesome.com -->
                </div>
                <a href="queries.php" class="small-box-footer" title="">
                  List all queries <svg class="svg-inline--fa fa-arrow-circle-right fa-w-16" aria-hidden="true" focusable="false" data-prefix="fa" data-icon="arrow-circle-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 8c137 0 248 111 248 248S393 504 256 504 8 393 8 256 119 8 256 8zm-28.9 143.6l75.5 72.4H120c-13.3 0-24 10.7-24 24v16c0 13.3 10.7 24 24 24h182.6l-75.5 72.4c-9.7 9.3-9.9 24.8-.4 34.3l11 10.9c9.4 9.4 24.6 9.4 33.9 0L404.3 273c9.4-9.4 9.4-24.6 0-33.9L271.6 106.3c-9.4-9.4-24.6-9.4-33.9 0l-11 10.9c-9.5 9.6-9.3 25.1.4 34.4z"></path></svg><!-- <i class="fa fa-arrow-circle-right"></i> Font Awesome fontawesome.com -->
                </a>
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-sm-6">
              <!-- small box -->
              <div class="small-box bg-green no-user-select" title="Adlists updated 04:18 (hh:mm) ago">
                <div class="inner">
                  <p>Domains on Adlists</p>
                  <h3 class="statistic">
                    <span id="domains_being_blocked">---</span>
                  </h3>
                </div>
                <div class="icon">
                  <svg class="svg-inline--fa fa-list-alt fa-w-16" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="list-alt" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M464 480H48c-26.51 0-48-21.49-48-48V80c0-26.51 21.49-48 48-48h416c26.51 0 48 21.49 48 48v352c0 26.51-21.49 48-48 48zM128 120c-22.091 0-40 17.909-40 40s17.909 40 40 40 40-17.909 40-40-17.909-40-40-40zm0 96c-22.091 0-40 17.909-40 40s17.909 40 40 40 40-17.909 40-40-17.909-40-40-40zm0 96c-22.091 0-40 17.909-40 40s17.909 40 40 40 40-17.909 40-40-17.909-40-40-40zm288-136v-32c0-6.627-5.373-12-12-12H204c-6.627 0-12 5.373-12 12v32c0 6.627 5.373 12 12 12h200c6.627 0 12-5.373 12-12zm0 96v-32c0-6.627-5.373-12-12-12H204c-6.627 0-12 5.373-12 12v32c0 6.627 5.373 12 12 12h200c6.627 0 12-5.373 12-12zm0 96v-32c0-6.627-5.373-12-12-12H204c-6.627 0-12 5.373-12 12v32c0 6.627 5.373 12 12 12h200c6.627 0 12-5.373 12-12z"></path></svg><!-- <i class="fas fa-list-alt"></i> Font Awesome fontawesome.com -->
                </div>
                <a href="groups-adlists.php" class="small-box-footer" title="">
                  Manage adlists <svg class="svg-inline--fa fa-arrow-circle-right fa-w-16" aria-hidden="true" focusable="false" data-prefix="fa" data-icon="arrow-circle-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 8c137 0 248 111 248 248S393 504 256 504 8 393 8 256 119 8 256 8zm-28.9 143.6l75.5 72.4H120c-13.3 0-24 10.7-24 24v16c0 13.3 10.7 24 24 24h182.6l-75.5 72.4c-9.7 9.3-9.9 24.8-.4 34.3l11 10.9c9.4 9.4 24.6 9.4 33.9 0L404.3 273c9.4-9.4 9.4-24.6 0-33.9L271.6 106.3c-9.4-9.4-24.6-9.4-33.9 0l-11 10.9c-9.5 9.6-9.3 25.1.4 34.4z"></path></svg><!-- <i class="fa fa-arrow-circle-right"></i> Font Awesome fontawesome.com -->
                </a>
              </div>
            </div>
            <!-- ./col -->
          </div>

          <div class="columns">
            <aside id="region_1" class="col-md-4 d-md-block col-lg-4 col-xl-3 d-none d-lg-block">
              <div class="aside_spacer_top_left"></div>
              <div class="aside_spacer_left">
                <div id="left_aside_wrapper" class="aside_wrapper">
                  <?php if(x($page,'aside')) echo $page['aside']; ?>
                </div>
              </div>
            </aside>
            <section id="region_2" class="col-12 col-md-8 col-lg-8 col-xl-6">
              <?php if(x($page,'content')) echo $page['content']; ?>
              <div id="page-footer"></div>
              <div id="pause"></div>
            </section>
            <aside id="region_3" class="d-lg-block col-xl-3 d-none d-xl-block">
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
      </main>
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
    </div>
    <!-- ./wrapper -->
  </body>
</html>
