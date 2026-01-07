<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<div class="section-content-wrapper">
		{{if $now}}
		<div class="section-content-danger-wrapper">
			<div class="h3">{{$now}}</div>
		</div>
		{{else}}
		<div class="section-content-warning-wrapper">
			{{$strings.0}}
			<div id="countdown" class="h3"></div>
		</div>
		<div class="section-content-info-wrapper">
			{{$strings.1}} {{$id}}
			<div class="h3">{{$pin}}</div>
			{{if $strings.2}}<b>{{$strings.2}}</b>{{/if}}
		</div>
		<div class="d-none">
			{{$strings.3}}<br>
			<span id="register_start" data-utc="{{$regdelay}}" class="register_date">
				{{$regdelay}}
			</span>
			&nbsp;&dash;&nbsp;
			<span data-utc="{{$regexpire}}" class="register_date">
				{{$regexpire}}
			</span>
		</div>
		{{/if}}
	</div>
</div>

<script>
	$('.register_date').each( function () {
		var date = new Date($(this).data('utc'));
		$(this).html(date.toLocaleString(undefined, {weekday: 'short', hour: 'numeric', minute: 'numeric'}));
	});

	var date = '{{$countdown}}';
	date = date !== '' ? date : $('#register_start').data('utc');

	if(date) {
		doCountDown(date, 'countdown');
		var x = setInterval(doCountDown, 1000, date, 'countdown');
	}

	function doCountDown(date, id) {
		var countDownDate = new Date(date).getTime();
		var now = new Date().getTime();
		var distance = countDownDate - now;
		var days = Math.floor(distance / (1000 * 60 * 60 * 24));
		var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
		var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
		var seconds = Math.floor((distance % (1000 * 60)) / 1000);

		document.getElementById(id).innerHTML = days + "d " + hours + "h "+ minutes + "m " + seconds + "s ";

		if (distance < 0) {
			clearInterval(x);
			document.getElementById(id).innerHTML = 'Reloading...';
			window.location.reload();
		}
	}
</script>
