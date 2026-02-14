<script>
  // update pending count //
  $(function () {

    $("nav").bind('nav-update', function (e, data) {
      var elm = $('#pending-update');
      var register = $(data).find('register').text();
      if (register == "0") {register = ""; elm.hide();} else {elm.show();}
      elm.html(register);
    });
  });
</script>
  <div class="h6">{{$admtxt}}</div>
  <ul class="link-list">
    {{foreach $admin as $link}}
    <li><a href='{{$link.0}}'>{{$link.1}}{{if $link.3}}<span id='{{$link.3}}'
          title='{{$link.4}}'></span>{{/if}}</a></li>
    {{/foreach}}
  </ul>

{{if $admin.update}}
  <ul class="link-list">
    <li><a class="nav-link" href='{{$admin.update.0}}'>{{$admin.update.1}}</a></li>
    <li><a class="nav-link" href=''>Important Changes</a></li>
  </ul>
{{/if}}


{{if $plugins}}
  <div class="h6">{{$plugadmtxt}}</div>
  <ul class="link-list">
    {{foreach $plugins as $l}}
    <li><a href='{{$l.0}}'>{{$l.1}}</a></li>
    {{/foreach}}
  </ul>
{{/if}}

  <div class="h6">{{$logtxt}}</div>
  <ul class="link-list">
    <li ><a href='{{$logs.0}}'>{{$logs.1}}</a></li>
  </ul>
