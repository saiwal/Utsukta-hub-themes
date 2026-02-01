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
	<div class="lcars-text-bar"><span>{{$admtxt}}</span></div>
  <div class="pillbox">
    {{foreach $admin as $link}}
    <button class="pill" onclick="playSoundAndRedirect('audio2', '{{$link.0}}')">{{$link.1}}{{if $link.3}}<span id='{{$link.3}}'
          title='{{$link.4}}'></span>{{/if}}</button>
    {{/foreach}}
  </div>
</div>

{{if $admin.update}}
<div class="mb-3">
  <div class="pillbox">
    <button class="pill" onclick="playSoundAndRedirect('audio2','{{$admin.update.0}}')">{{$admin.update.1}}</button>
    <button class="pill" href=''>Important Changes</button>
  </div>
</div>
{{/if}}


{{if $plugins}}
<div class="mb-3">
	<div class="lcars-text-bar"><span>{{$plugadmtxt}}</span></div>
  <div class="pillbox">
    {{foreach $plugins as $l}}
		<button class="pill" onclick="playSoundAndRedirect('audio2','{{$l.0}}')">{{$l.1}}</button>
    {{/foreach}}
  </div>
</div>
{{/if}}

<div class="mb-3">
	<div class="lcars-text-bar"><span>{{$logtxt}}</span></div>
  <div class="pillbox">
    <button class="pill" onclick="playSoundAndRedirect('audio2','{{$logs.0}}')">{{$logs.1}}</button>
  </div>
</div>
