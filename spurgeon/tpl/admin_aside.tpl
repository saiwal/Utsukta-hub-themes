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
<div class="mb-3">
  <div class="h5">{{$admtxt}}</div>
  <ul class="list-unstyled">
    {{foreach $admin as $link}}
    <li class="mt-0"><a href='{{$link.0}}'>{{$link.1}}{{if $link.3}}<span id='{{$link.3}}'
          title='{{$link.4}}'></span>{{/if}}</a></li>
    {{/foreach}}
  </ul>
</div>

{{if $admin.update}}
<div class="mb-3">
  <ul class="list-unstyled">
    <li class="mt-0"><a href='{{$admin.update.0}}'>{{$admin.update.1}}</a></li>
    <li class="mt-0"><a href=''>Important Changes</a></li>
  </ul>
</div>
{{/if}}


{{if $plugins}}
<div class="mb-3">
  <div class="h5">{{$plugadmtxt}}</div>
  <ul class="list-unstyled">
    {{foreach $plugins as $l}}
    <li class="mt-0"><a href='{{$l.0}}'>{{$l.1}}</a></li>
    {{/foreach}}
  </ul>
</div>
{{/if}}

<div class="mb-3">
  <div class="h5">{{$logtxt}}</div>
  <ul class="list-unstyled">
    <li class="mt-0"><a href='{{$logs.0}}'>{{$logs.1}}</a></li>
  </ul>
</div>
