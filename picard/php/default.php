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
                      src="img/logo.svg"
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
                        <use xlink:href="img/pihole_icon.svg#pihole-svg-logo" />
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
              <img class="logo-img" src="img/logo.svg" alt="Pi-hole logo" />
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
                      <use xlink:href="img/pihole_icon.svg#pihole-svg-logo" />
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
                      <use xlink:href="img/pihole_icon.svg#pihole-svg-logo" />
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
          <!-- Small boxes (Stat box) -->
          <div class="row">
            <div class="col-lg-3 col-sm-6">
              <!-- small box -->
              <div
                class="small-box bg-aqua no-user-select"
                id="total_queries"
                title="only A + AAAA queries"
              >
                <div class="inner">
                  <p>Total queries</p>
                  <h3 class="statistic">
                    <span id="dns_queries_today">---</span>
                  </h3>
                </div>
                <div class="icon">
                  <i class="fas fa-globe-americas"></i>
                </div>
                <a href="network.php" class="small-box-footer" title="">
                  <span id="unique_clients">-</span> active clients
                  <i class="fa fa-arrow-circle-right"></i>
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
                  <i class="fas fa-hand-paper"></i>
                </div>
                <a
                  href="queries.php?forwarddest=blocked"
                  class="small-box-footer"
                  title=""
                >
                  List blocked queries <i class="fa fa-arrow-circle-right"></i>
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
                  <i class="fas fa-chart-pie"></i>
                </div>
                <a href="queries.php" class="small-box-footer" title="">
                  List all queries <i class="fa fa-arrow-circle-right"></i>
                </a>
              </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-sm-6">
              <!-- small box -->
              <div
                class="small-box bg-green no-user-select"
                title="Adlists updated 04:18 (hh:mm) ago"
              >
                <div class="inner">
                  <p>Domains on Adlists</p>
                  <h3 class="statistic">
                    <span id="domains_being_blocked">---</span>
                  </h3>
                </div>
                <div class="icon">
                  <i class="fas fa-list-alt"></i>
                </div>
                <a href="groups-adlists.php" class="small-box-footer" title="">
                  Manage adlists <i class="fa fa-arrow-circle-right"></i>
                </a>
              </div>
            </div>
            <!-- ./col -->
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="box" id="queries-over-time">
                <div class="box-header with-border">
                  <h3 class="box-title">
                    Total queries over last
                    <span class="maxlogage-interval">24</span> hours
                  </h3>
                </div>
                <div class="box-body">
                  <div class="chart" style="width: 100%; height: 180px">
                    <canvas id="queryOverTimeChart"></canvas>
                  </div>
                </div>
                <div class="overlay">
                  <i class="fa fa-sync fa-spin"></i>
                </div>
                <!-- /.box-body -->
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-12">
              <div class="box" id="clients">
                <div class="box-header with-border">
                  <h3 class="box-title">
                    Client activity over last
                    <span class="maxlogage-interval">24</span> hours
                  </h3>
                </div>
                <div class="box-body">
                  <div class="chart" style="width: 100%; height: 180px">
                    <canvas
                      id="clientsChart"
                      class="extratooltipcanvas no-user-select"
                    ></canvas>
                  </div>
                </div>
                <div class="overlay">
                  <i class="fa fa-sync fa-spin"></i>
                </div>
                <!-- /.box-body -->
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="box" id="query-types-pie">
                <div class="box-header with-border">
                  <h3 class="box-title">Query Types</h3>
                </div>
                <div class="box-body">
                  <div style="width: 50%">
                    <canvas
                      id="queryTypePieChart"
                      width="280"
                      height="280"
                    ></canvas>
                  </div>
                  <div
                    class="chart-legend"
                    style="width: 50%"
                    id="query-types-legend"
                  ></div>
                </div>
                <div class="overlay">
                  <i class="fa fa-sync fa-spin"></i>
                </div>
                <!-- /.box-body -->
              </div>
            </div>
            <div class="col-md-6">
              <div class="box" id="forward-destinations-pie">
                <div class="box-header with-border">
                  <h3 class="box-title">Upstream servers</h3>
                </div>
                <div class="box-body">
                  <div style="width: 50%">
                    <canvas
                      id="forwardDestinationPieChart"
                      width="280"
                      height="280"
                      class="extratooltipcanvas no-user-select"
                    ></canvas>
                  </div>
                  <div
                    class="chart-legend"
                    style="width: 50%"
                    id="forward-destinations-legend"
                  ></div>
                </div>
                <div class="overlay">
                  <i class="fa fa-sync fa-spin"></i>
                </div>
                <!-- /.box-body -->
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 col-lg-6">
              <div class="box" id="domain-frequency">
                <div class="box-header with-border">
                  <h3 class="box-title">Top Permitted Domains</h3>
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                  <div class="table-responsive">
                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th>Domain</th>
                          <th>Hits</th>
                          <th>Frequency</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
                <div class="overlay">
                  <i class="fa fa-sync fa-spin"></i>
                </div>
                <!-- /.box-body -->
              </div>
              <!-- /.box -->
            </div>
            <!-- /.col -->
            <div class="col-md-6 col-lg-6">
              <div class="box" id="ad-frequency">
                <div class="box-header with-border">
                  <h3 class="box-title">Top Blocked Domains</h3>
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                  <div class="table-responsive">
                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th>Domain</th>
                          <th>Hits</th>
                          <th>Frequency</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
                <div class="overlay">
                  <i class="fa fa-sync fa-spin"></i>
                </div>
                <!-- /.box-body -->
              </div>
              <!-- /.box -->
            </div>
          </div>
          <div class="row">
            <!-- /.col -->
            <div class="col-md-6 col-lg-6">
              <div class="box" id="client-frequency">
                <div class="box-header with-border">
                  <h3 class="box-title">Top Clients (total)</h3>
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                  <div class="table-responsive">
                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th>Client</th>
                          <th>Requests</th>
                          <th>Frequency</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
                <div class="overlay">
                  <i class="fa fa-sync fa-spin"></i>
                </div>
                <!-- /.box-body -->
              </div>
              <!-- /.box -->
            </div>
            <!-- /.col -->
            <div class="col-md-6 col-lg-6">
              <div class="box" id="client-frequency-blocked">
                <div class="box-header with-border">
                  <h3 class="box-title">Top Clients (blocked only)</h3>
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                  <div class="table-responsive">
                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th>Client</th>
                          <th>Requests</th>
                          <th>Frequency</th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
                <div class="overlay">
                  <i class="fa fa-sync fa-spin"></i>
                </div>
                <!-- /.box-body -->
              </div>
              <!-- /.box -->
            </div>
            <!-- /.col -->
          </div>
          <!-- /.row -->

          <script src="scripts/pi-hole/js/index.js?v=1720203623"></script>
        </section>
        <!-- /.content -->
      </div>
      <!-- Modal for custom disable time -->
      <div
        class="modal fade"
        id="customDisableModal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="myModalLabel"
      >
        <div class="modal-dialog modal-sm" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button
                type="button"
                class="close"
                data-dismiss="modal"
                aria-label="Close"
              >
                <span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title" id="myModalLabel">
                Custom disable timeout
              </h4>
            </div>
            <div class="modal-body">
              <div class="input-group">
                <input
                  id="customTimeout"
                  class="form-control"
                  type="number"
                  value="60"
                />
                <div class="input-group-btn" data-toggle="buttons">
                  <label class="btn btn-default">
                    <input id="selSec" type="radio" /> Secs
                  </label>
                  <label id="btnMins" class="btn btn-default active">
                    <input id="selMin" type="radio" /> Mins
                  </label>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-default"
                data-dismiss="modal"
              >
                Close
              </button>
              <button
                type="button"
                id="pihole-disable-custom"
                class="btn btn-primary"
                data-dismiss="modal"
              >
                Submit
              </button>
            </div>
          </div>
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
    </div>
    <!-- ./wrapper -->
    <script src="scripts/pi-hole/js/footer.js?v=1720203623"></script>
  </body>
</html>
