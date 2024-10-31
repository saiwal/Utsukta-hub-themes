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
      <header class="main-header">
        <!-- Logo -->
        <a href="index.php" class="logo">
          <!-- mini logo for sidebar mini 50x50 pixels -->
          <span class="logo-mini">P<strong>h</strong></span>
          <!-- logo for regular state and mobile devices -->
          <span class="logo-lg">Pi-<strong>hole</strong></span>
        </a>
        <!-- Header Navbar: style can be found in header.less -->
        <nav class="navbar navbar-static-top">
          <!-- Sidebar toggle button-->
          <a
            href="#"
            class="sidebar-toggle-svg"
            data-toggle="push-menu"
            role="button"
          >
            <i aria-hidden="true" class="fa fa-angle-double-left"></i>
            <span class="sr-only">Toggle navigation</span>
            <span class="warning-count hidden" id="top-warning-count"></span>
          </a>
          <div class="navbar-custom-menu">
            <ul class="nav navbar-nav">
              <li>
                <p class="navbar-text">
                  <span class="hidden-xs">hostname:</span>
                  <code>pihole</code>
                </p>
              </li>
              <li class="dropdown user user-menu">
                <a
                  href="#"
                  class="dropdown-toggle"
                  data-toggle="dropdown"
                  aria-expanded="false"
                >
                  <i class="fa fa-bars"></i>
                </a>
                <ul class="dropdown-menu">
                  <!-- User image -->
                  <li class="user-header">
                    <img
                      class="logo-img"
                      src="/view/theme/picard/img/logo.svg"
                      alt="Pi-hole Logo"
                      style="border: 0"
                      width="90"
                      height="90"
                    />
                    <p>Open Source Ad Blocker</p>
                  </li>
                  <!-- Menu Body -->
                  <!-- <li class="user-body"></li> -->
                  <!-- Menu Footer -->
                  <li class="user-footer">
                    <a
                      class="btn-link"
                      href="https://pi-hole.net/"
                      rel="noopener"
                      target="_blank"
                    >
                      <svg
                        class="svg-inline--fa fa-fw menu-icon"
                        style="height: 1.25em"
                      >
                        <use xlink:href="/view/theme/picard/img/pihole_icon.svg#pihole-svg-logo" />
                      </svg>
                      Pi-hole Website
                    </a>
                    <hr />
                    <a
                      class="btn-link"
                      href="https://docs.pi-hole.net/"
                      rel="noopener"
                      target="_blank"
                      ><i class="fa fa-fw menu-icon fa-question-circle"></i>
                      Documentation</a
                    >
                    <a
                      class="btn-link"
                      href="https://discourse.pi-hole.net/"
                      rel="noopener"
                      target="_blank"
                      ><i class="fa fa-fw menu-icon fab fa-discourse"></i>
                      Pi-hole Forum</a
                    >
                    <a
                      class="btn-link"
                      href="https://github.com/pi-hole"
                      rel="noopener"
                      target="_blank"
                      ><i class="fa-fw menu-icon fab fa-github"></i> GitHub</a
                    >
                    <a
                      class="btn-link"
                      href="https://discourse.pi-hole.net/c/announcements/5"
                      rel="noopener"
                      target="_blank"
                      ><i class="fa-fw menu-icon fa fa-regular fa-rocket"></i>
                      Pi-hole Releases</a
                    >
                    <hr />
                    <a class="btn-link" href="logout.php" rel="noopener"
                      ><i class="fa fa-fw menu-icon fa-sign-out-alt"></i> Log
                      out</a
                    >
                  </li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>
      </header>
      <!-- Left side column. contains the logo and sidebar -->
      <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <section class="sidebar">
          <!-- Sidebar user panel -->
          <div class="user-panel">
            <div class="pull-left image">
              <img class="logo-img" src="/view/theme/picard/img/logo.svg" alt="Pi-hole logo" />
            </div>
            <div class="pull-left info">
              <p>Status</p>
              <span id="status"
                ><i class="fa fa-w fa-circle text-green-light"></i> Active</span
              >
              <br />
              <span title="Detected 4 cores"
                ><i class="fa fa-w fa-circle text-green-light"></i>
                Load:&nbsp;&nbsp;0.73&nbsp;&nbsp;0.99&nbsp;&nbsp;0.86</span
              >
              <br />
              <span
                ><i class="fa fa-w fa-circle text-green-light"></i> Memory
                usage:&nbsp;&nbsp;7.7&thinsp;%</span
              >
              <br />
              <span id="temperature"
                ><i
                  class="fa fa-w fa-fire text-red"
                  style="width: 1em !important"
                ></i>
                Temp:&nbsp;<span id="rawtemp" hidden>70</span
                ><span id="tempunit" hidden></span><span id="tempdisplay"></span
              ></span>
            </div>
          </div>
          <!-- sidebar menu: : style can be found in sidebar.less -->
          <ul class="sidebar-menu" data-widget="tree">
            <li class="header text-uppercase">Main</li>
            <!-- Home Page -->
            <li class="menu-main active">
              <a href="index.php">
                <i class="fa fa-fw menu-icon fa-home"></i>
                <span>Dashboard</span>
              </a>
            </li>

            <li class="header text-uppercase">Analysis</li>
            <!-- Query Log -->
            <li class="menu-analysis">
              <a href="queries.php">
                <i class="fa fa-fw menu-icon fa-file-alt"></i>
                <span>Query Log</span>
              </a>
            </li>
            <!-- Long-term database -->
            <li class="menu-analysis treeview">
              <a href="#">
                <i class="fa fa-fw menu-icon fa-history"></i>
                <span>Long-term Data</span>
                <span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
              </a>
              <ul class="treeview-menu">
                <li class="">
                  <a href="db_graph.php">
                    <i class="fa fa-fw menu-icon fa-chart-bar"></i> Graphics
                  </a>
                </li>
                <li class="">
                  <a href="db_queries.php">
                    <i class="fa fa-fw menu-icon fa-file-alt"></i> Query Log
                  </a>
                </li>
                <li class="">
                  <a href="db_lists.php">
                    <i class="fa fa-fw menu-icon fa-list"></i> Top Lists
                  </a>
                </li>
              </ul>
            </li>

            <li class="header text-uppercase">Group Management</li>
            <!-- Group Management -->
            <li class="menu-group">
              <a href="groups.php">
                <i class="fa fa-fw menu-icon fa-user-friends"></i>
                <span>Groups</span>
              </a>
            </li>
            <li class="menu-group">
              <a href="groups-clients.php">
                <i class="fa fa-fw menu-icon fa-laptop"></i>
                <span>Clients</span>
              </a>
            </li>
            <li class="menu-group">
              <a href="groups-domains.php">
                <i class="fa fa-fw menu-icon fa-list"></i> <span>Domains</span>
              </a>
            </li>
            <li class="menu-group">
              <a href="groups-adlists.php">
                <i class="fa fa-fw menu-icon fa-shield-alt"></i>
                <span>Adlists</span>
              </a>
            </li>

            <li class="header text-uppercase">DNS Control</li>
            <!-- Local DNS Records -->
            <!-- Enable/Disable Blocking -->
            <li id="pihole-disable" class="menu-dns treeview">
              <a href="#">
                <i class="fa fa-fw menu-icon fa-stop"></i>
                <span
                  >Disable Blocking&nbsp;&nbsp;&nbsp;<span
                    id="flip-status-disable"
                  ></span
                ></span>
                <span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
              </a>
              <ul class="treeview-menu">
                <li>
                  <a href="#" id="pihole-disable-indefinitely">
                    <i class="fa fa-fw menu-icon fa-infinity"></i> Indefinitely
                  </a>
                </li>
                <li>
                  <a href="#" id="pihole-disable-10s">
                    <i class="fa fa-fw menu-icon fa-clock"></i> For 10 seconds
                  </a>
                </li>
                <li>
                  <a href="#" id="pihole-disable-30s">
                    <i class="fa fa-fw menu-icon fa-clock"></i> For 30 seconds
                  </a>
                </li>
                <li>
                  <a href="#" id="pihole-disable-5m">
                    <i class="fa fa-fw menu-icon fas fa-clock"></i> For 5
                    minutes
                  </a>
                </li>
                <li>
                  <a
                    href="#"
                    id="pihole-disable-cst"
                    data-toggle="modal"
                    data-target="#customDisableModal"
                  >
                    <i class="fa fa-fw menu-icon fa-user-clock"></i> Custom time
                  </a>
                </li>
              </ul>
              <!-- <a href="#" id="flip-status"><i class="fa fa-stop"></i> <span>Disable</span></a> -->
            </li>
            <li id="pihole-enable" class="menu-dns treeview" hidden>
              <a href="#">
                <i class="fa fa-fw menu-icon fa-play"></i>
                <span id="enableLabel"
                  >Enable Blocking&nbsp;&nbsp;&nbsp;
                  <span id="flip-status-enable"></span>
                </span>
              </a>
            </li>
            <li class="menu-dns treeview">
              <a href="#">
                <i class="fa fa-fw menu-icon fa-address-book"></i>
                <span>Local DNS</span>
                <span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
              </a>
              <ul class="treeview-menu">
                <li class="">
                  <a href="dns_records.php">
                    <i class="fa fa-fw menu-icon fa-address-book"></i> DNS
                    Records
                  </a>
                </li>
                <li class="">
                  <a href="cname_records.php">
                    <i class="fa fa-fw menu-icon fa-address-book"></i> CNAME
                    Records
                  </a>
                </li>
              </ul>
            </li>

            <li class="header text-uppercase">System</li>
            <!-- Tools -->
            <li class="menu-system treeview">
              <a href="#">
                <i class="fa fa-fw menu-icon fa-tools"></i> <span>Tools</span>
                <span class="warning-count hidden"></span>
                <span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
              </a>
              <ul class="treeview-menu">
                <!-- Pi-hole diagnosis -->
                <li class="">
                  <a href="messages.php">
                    <i class="fa fa-fw menu-icon fa-file-medical-alt"></i>
                    Pi-hole diagnosis
                    <span
                      class="pull-right-container warning-count hidden"
                    ></span>
                  </a>
                </li>
                <!-- Run gravity.sh -->
                <li class="">
                  <a href="gravity.php">
                    <i class="fa fa-fw menu-icon fa-arrow-circle-down"></i>
                    Update Gravity
                  </a>
                </li>
                <!-- Query Lists -->
                <li class="">
                  <a href="queryads.php">
                    <i class="fa fa-fw menu-icon fa-search"></i> Search Adlists
                  </a>
                </li>
                <!-- Audit log -->
                <li class="">
                  <a href="auditlog.php">
                    <i class="fa fa-fw menu-icon fa-balance-scale"></i> Audit
                    log
                  </a>
                </li>
                <!-- Tail pihole.log -->
                <li class="">
                  <a href="taillog.php">
                    <svg
                      class="svg-inline--fa fa-fw menu-icon"
                      style="height: 1.25em"
                    >
                      <use xlink:href="/view/theme/picard/img/pihole_icon.svg#pihole-svg-logo" />
                    </svg>
                    Tail pihole.log
                  </a>
                </li>
                <!-- Tail FTL.log -->
                <li class="">
                  <a href="taillog-FTL.php">
                    <svg
                      class="svg-inline--fa fa-fw menu-icon"
                      style="height: 1.25em"
                    >
                      <use xlink:href="/view/theme/picard/img/pihole_icon.svg#pihole-svg-logo" />
                    </svg>
                    Tail FTL.log
                  </a>
                </li>
                <!-- Generate debug log -->
                <li class="">
                  <a href="debug.php">
                    <i class="fa fa-fw menu-icon fa-ambulance"></i> Generate
                    debug log
                  </a>
                </li>
                <!-- Network -->
                <li class="">
                  <a href="network.php">
                    <i class="fa fa-fw menu-icon fa-network-wired"></i> Network
                  </a>
                </li>
              </ul>
            </li>
            <!-- Settings -->
            <li class="menu-system">
              <a href="settings.php">
                <i class="fa fa-fw menu-icon fa-cog"></i> <span>Settings</span>
              </a>
            </li>

            <!-- Donate button -->
            <li class="header text-uppercase">Donate</li>
            <li class="menu-donate">
              <a href="https://pi-hole.net/donate/" target="_blank">
                <i class="fas fa-fw menu-icon fa-donate"></i>
                <span>Donate</span>
              </a>
            </li>
          </ul>
        </section>
        <!-- /.sidebar -->
      </aside>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Main content -->
        <section class="content">
        <?php if(x($page,'content')) echo $page['content']; ?>
        </div>
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
