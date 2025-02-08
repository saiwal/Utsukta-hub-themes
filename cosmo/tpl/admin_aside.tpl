<script>
	// update pending count //
	$(function(){

		$("nav").bind('nav-update',  function(e,data){
			var elm = $('#pending-update');
			var register = $(data).find('register').text();
			if (register=="0") { register=""; elm.hide();} else { elm.show(); }
			elm.html(register);
		});
	});
</script>
<div class="card mb-3">
  <div class="card-header">{{$admtxt}}</div>
  <div class="card-body">  
    <ul class="nav nav-pills flex-column">
    	{{foreach $admin as $link}}
      	<li class="nav-item"><a class="nav-link" href='{{$link.0}}'>{{$link.1}}{{if $link.3}}<span id='{{$link.3}}' title='{{$link.4}}'></span>{{/if}}</a></li>
    	{{/foreach}}
    </ul>
  </div>
</div>

{{if $admin.update}}
<div class="card mb-3">
<ul class="nav nav-pills flex-column">
	<li class="nav-item"><a class="nav-link" href='{{$admin.update.0}}'>{{$admin.update.1}}</a></li>
	<li class="nav-item"><a class="nav-link" href=''>Important Changes</a></li>
</ul>
</div>
{{/if}}


{{if $plugins}}
<div class="card mb-3">
<div class="card-header">{{$plugadmtxt}}</div>
<ul class="nav nav-pills flex-column">
	{{foreach $plugins as $l}}
	<li class="nav-item"><a class="nav-link" href='{{$l.0}}'>{{$l.1}}</a></li>
	{{/foreach}}
</ul>
</div>
{{/if}}
	
<div class="card mb-3">
<div class="card-header">{{$logtxt}}</div>
<ul class="nav nav-pills flex-column">
	<li class="nav-item"><a class="nav-link" href='{{$logs.0}}'>{{$logs.1}}</a></li>
</ul>
</div>
