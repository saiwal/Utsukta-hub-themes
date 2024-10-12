<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php if(x($page,'title')) echo $page['title'] ?></title>
  <?php if(x($page,'htmlhead')) echo $page['htmlhead'] ?>
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini sidebar-collapse bg-body-tertiary app-loaded">
<div class="app-wrapper">

  <?php if(x($page,'nav')) echo $page['nav']; ?>

   <!-- Content Wrapper. Contains page content -->
  <div class="app-main px-2 py-2">
      <?php if(x($page,'content')) echo $page['content']; ?>
  </div>
  <!-- /.content-wrapper -->


  <!-- Main Footer -->
  <footer class="app-footer text-sm">
    🖖 Live long and prosper.
    <div class="float-right d-none d-sm-inline-block">
    </div>
  </footer>
</div>
<!-- ./wrapper -->

</body>
</html>

