<nav class="app-header navbar navbar-expand bg-body border-0 sticky-top"> <!--begin::Container-->
  <div class="container-fluid"> <!--begin::Start Navbar Links-->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-layout-sidebar"></i></a>
      </li>
      <li class="nav-item">
        <a class="nav-link d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive" aria-controls="offcanvasResponsive"><i class="bi bi-layout-text-sidebar"></i></a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Navbar Search -->
      <li class="nav-item">
        <div class="navbar-search-block">
          <form class="form-inline" method="get" action="{{$nav.search.4}}" role="search">
						<input class="form-control form-control-sm mt-1 me-2" id="nav-search-text" type="text" value="" placeholder="{{$help}}" name="search" title="{{$nav.search.3}}" onclick="this.submit();" onblur="closeMenu('nav-search'); openMenu('nav-search-btn');"/>
					</form>
        </div>
      </li>

      {{if $localuser || $nav.pubs}}
      <li class="nav-item dropdown"> <a class="nav-link show" data-bs-toggle="dropdown" href="#" aria-expanded="true"> <i class="bi bi-bell-fill"></i> <span class="navbar-badge badge text-bg-warning">15</span> </a>
          <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" data-bs-popper="static"> 
 <script>
	var sse_bs_active = false;
	var sse_offset = 0;
	var sse_type;
	var sse_partial_result = false;
	var sse_rmids = [];
	var sse_fallback_interval;
	var sse_sys_only = {{$sys_only}};

	$(document).ready(function() {
		let notifications_parent;
		if ($('#notifications_wrapper').length) {
			notifications_parent = $('#notifications_wrapper')[0].parentElement.id;
		}

		$('.notifications-btn').click(function() {
			$('#notifications_wrapper').removeClass('d-none');

			if($('#notifications_wrapper').hasClass('fs')) {
				$('#notifications_wrapper').prependTo('#' + notifications_parent);
				$('#notifications_wrapper').addClass('d-none');

			}
			else {
				$('#notifications_wrapper').prependTo('main');
			}

			$('#notifications_wrapper').toggleClass('fs');
			if($('#navbar-collapse-2').hasClass('show')){
				$('#navbar-collapse-2').removeClass('show');
			}
		});

		$(document).on('click', '.notification', function() {
			if($('#notifications_wrapper').hasClass('fs')) {
				$('#notifications_wrapper').prependTo('#' + notifications_parent).removeClass('fs').addClass('d-none');
			}
		});

		if(sse_enabled) {
			if(typeof(window.SharedWorker) === 'undefined') {
				// notifications with multiple tabs open will not work very well in this scenario
				var evtSource = new EventSource('/sse');

				evtSource.addEventListener('notifications', function(e) {
					var obj = JSON.parse(e.data);
					sse_handleNotifications(obj, false, false);
				}, false);

				document.addEventListener('visibilitychange', function() {
					if (!document.hidden) {
						sse_offset = 0;
						sse_bs_init();
					}
				}, false);

			}
			else {
				var myWorker = new SharedWorker('/view/js/sse_worker.js', localUser);

				myWorker.port.onmessage = function(e) {
					obj = e.data;
					console.log(obj);
					sse_handleNotifications(obj, false, false);
				}

				myWorker.onerror = function(e) {
					myWorker.port.close();
				}

				myWorker.port.start();
			}
		}
		else {
			if (!document.hidden)
				sse_fallback_interval = setInterval(sse_fallback, updateInterval);

			document.addEventListener('visibilitychange', function() {
				if (document.hidden) {
					clearInterval(sse_fallback_interval);
				}
				else {
					sse_offset = 0;
					sse_bs_init();
					sse_fallback_interval = setInterval(sse_fallback, updateInterval);
				}

			}, false);
		}

		$('.notification-link').on('click', { replace: true, followup: false }, sse_bs_notifications);

		$('.notification-filter').on('keypress', function(e) {
			if(e.which == 13) { // enter
				this.blur();
				sse_offset = 0;
				$("#nav-" + sse_type + "-menu").html('');
				$("#nav-" + sse_type + "-loading").show();

				var cn_val = $('#cn-' + sse_type + '-input').length ? $('#cn-' + sse_type + '-input').val().toString().toLowerCase() : '';

				$.get('/sse_bs/' + sse_type + '/' + sse_offset + '?nquery=' + encodeURIComponent(cn_val), function(obj) {
					console.log('sse: bootstraping ' + sse_type);
					console.log(obj);

					sse_bs_active = false;
					sse_partial_result = true;
					sse_offset = obj[sse_type].offset;
					if(sse_offset < 0)
						$("#nav-" + sse_type + "-loading").hide();

					sse_handleNotifications(obj, true, false);

				});
			}
		});

		$('.notifications-textinput-clear').on('click', function(e) {
			if(! sse_partial_result)
				return;

			$("#nav-" + sse_type + "-menu").html('');
			$("#nav-" + sse_type + "-loading").show();
			$.get('/sse_bs/' + sse_type, function(obj) {
				console.log('sse: bootstraping ' + sse_type);
				console.log(obj);

				sse_bs_active = false;
				sse_partial_result = false;
				sse_offset = obj[sse_type].offset;
				if(sse_offset < 0)
					$("#nav-" + sse_type + "-loading").hide();

				sse_handleNotifications(obj, true, false);

			});
		});

		$('.notification-content').on('scroll', function() {
			if(this.scrollTop > this.scrollHeight - this.clientHeight - (this.scrollHeight/7)) {
				sse_bs_notifications(sse_type, false, true);
			}
		});

	});

	$(document).on('hz:sse_setNotificationsStatus', function(e, data) {
		sse_setNotificationsStatus(data);
	});

	$(document).on('hz:sse_bs_init', function() {
		sse_bs_init();
	});

	$(document).on('hz:sse_bs_counts', function() {
		sse_bs_counts();
	});

	{{foreach $notifications as $notification}}
	{{if $notification.filter}}
	$(document).on('click', '#tt-{{$notification.type}}-only', function(e) {
		if($(this).hasClass('active sticky-top')) {
			$('#nav-{{$notification.type}}-menu .notification[data-thread_top=false]').removeClass('tt-filter-active');
			$(this).removeClass('active sticky-top');
		}
		else {
			$('#nav-{{$notification.type}}-menu .notification[data-thread_top=false]').addClass('tt-filter-active');
			$(this).addClass('active sticky-top');
			// load more notifications if visible notifications count is low
			if(sse_type  && sse_offset != -1 && $('#nav-' + sse_type + '-menu').children(':visible').length < 15) {
				sse_bs_notifications(sse_type, false, true);
			}
		}

	});

	$(document).on('click', '#cn-{{$notification.type}}-input-clear', function(e) {
		$('#cn-{{$notification.type}}-input').val('');
		$('#cn-{{$notification.type}}-only').removeClass('active sticky-top');
		$("#nav-{{$notification.type}}-menu .notification").removeClass('cn-filter-active');
		$('#cn-{{$notification.type}}-input-clear').addClass('d-none');
	});

	$(document).on('input', '#cn-{{$notification.type}}-input', function(e) {
		var val = $('#cn-{{$notification.type}}-input').val().toString().toLowerCase();
		if(val) {
			val = val.indexOf('%') == 0 ? val.substring(1) : val;
			$('#cn-{{$notification.type}}-only').addClass('active sticky-top');
			$('#cn-{{$notification.type}}-input-clear').removeClass('d-none');
		}
		else {
			$('#cn-{{$notification.type}}-only').removeClass('active sticky-top');
			$('#cn-{{$notification.type}}-input-clear').addClass('d-none');
		}

		$("#nav-{{$notification.type}}-menu .notification").each(function(i, el){
			var cn = $(el).data('contact_name').toString().toLowerCase();
			var ca = $(el).data('contact_addr').toString().toLowerCase();

			if(cn.indexOf(val) === -1 && ca.indexOf(val) === -1)
				$(this).addClass('cn-filter-active');
			else
				$(this).removeClass('cn-filter-active');
		});
	});
	{{/if}}
	{{/foreach}}

	function sse_bs_init() {
		if(sessionStorage.getItem('notification_open') !== null || typeof sse_type !== 'undefined' ) {
			if(typeof sse_type === 'undefined')
				sse_type = sessionStorage.getItem('notification_open');

			$("#nav-" + sse_type + "-sub").addClass('show');
			sse_bs_notifications(sse_type, true, false);
		}
		else {
			sse_bs_counts();
		}
	}

	function sse_bs_counts() {
		if(sse_bs_active || sse_sys_only) {
			return;
		}

		sse_bs_active = true;

		$.ajax({
			type: 'post',
			url: '/sse_bs',
			data: { sse_rmids }
		}).done( function(obj) {
			console.log(obj);
			sse_bs_active = false;
			sse_rmids = [];
			sse_handleNotifications(obj, true, false);
		});
	}

	function sse_bs_notifications(e, replace, followup) {

		if(sse_bs_active || sse_sys_only) {
			return;
		}

		let manual = false;

		if(typeof replace === 'undefined')
			replace = e.data.replace;

		if(typeof followup === 'undefined')
			followup = e.data.followup;

		if(typeof e === 'string') {
			sse_type = e;
		}
		else {
			manual = true;
			sse_offset = 0;
			sse_type = e.target.dataset.sse_type;
		}

		if(typeof sse_type === 'undefined')
			return;

		if(followup || !manual || !$('#notification-link-' + sse_type).hasClass('collapsed')) {

			if(sse_offset >= 0) {
				$("#nav-" + sse_type + "-loading").show();
			}

			sessionStorage.setItem('notification_open', sse_type);
			if(sse_offset !== -1 || replace) {

				var cn_val = (($('#cn-' + sse_type + '-input').length && sse_partial_result) ? $('#cn-' + sse_type + '-input').val().toString().toLowerCase() : '');

				$("#nav-" + sse_type + "-loading").show();

				sse_bs_active = true;

				$.ajax({
					type: 'post',
					url: '/sse_bs/' + sse_type + '/' + sse_offset,
					nquery: encodeURIComponent(cn_val),
					data: { sse_rmids }
				}).done(function(obj) {
					console.log('sse: bootstraping ' + sse_type);
					console.log(obj);
					sse_bs_active = false;
					sse_rmids = [];
					$("#nav-" + sse_type + "-loading").hide();
					sse_offset = obj[sse_type].offset;
					sse_handleNotifications(obj, replace, followup);
				});
			}
			else {
				$("#nav-" + sse_type + "-loading").hide();
			}
		}
		else {
			sessionStorage.removeItem('notification_open');
		}
	}

	function sse_handleNotifications(obj, replace, followup) {

		// notice and info

		if(obj.notice) {
			$(obj.notice.notifications).each(function() {
				toast(this, 'danger');
			});
		}

		if(obj.info) {
			$(obj.info.notifications).each(function(){
				toast(this, 'info');
			});
		}

		if (sse_sys_only) {
			return;
		}

		let primary_notifications = ['dm', 'home', 'intros', 'register', 'notify', 'files'];
		let secondary_notifications = ['network', 'forums', 'all_events', 'pubs'];
		let all_notifications = primary_notifications.concat(secondary_notifications);

		all_notifications.forEach(function(type, index) {
			if(typeof obj[type] === typeof undefined)
				return true;

			var count = Number(obj[type].count);

			if(obj[type].count) {
				$('.' + type + '-button').fadeIn();
				if(replace || followup) {
					$('.' + type + '-update').html(count >= 100 ? '99+' : count);
				}
				else {
					count = count + Number($('.' + type + '-update').html().replace(/\++$/, ''));
					$('.' + type + '-update').html(count >= 100 ? '99+' : count);
				}
			}
			else {
				$('.' + type + '-update').html('0');
				$('#nav-' + type + '-sub').removeClass('show');
				$('.' + type + '-button').fadeOut(function() {
					sse_setNotificationsStatus();
				});
			}
			if(obj[type].notifications.length)
				sse_handleNotificationsItems(type, obj[type].notifications, replace, followup);
		});

		sse_setNotificationsStatus();

		// load more notifications if visible notifications count becomes low
		if(sse_type  && sse_offset != -1 && $('#nav-' + sse_type + '-menu').children(':not(.tt-filter-active)').length < 15) {
			sse_bs_notifications(sse_type, false, true);
		}


	}

	function sse_handleNotificationsItems(notifyType, data, replace, followup) {

		let notifications_tpl = ((notifyType == 'forums') ? decodeURIComponent($("#nav-notifications-forums-template[rel=template]").html().replace('data-src', 'src')) : decodeURIComponent($("#nav-notifications-template[rel=template]").html().replace('data-src', 'src')));
		let notify_menu = $("#nav-" + notifyType + "-menu");
		let notify_loading = $("#nav-" + notifyType + "-loading");
		let notify_count = $("." + notifyType + "-update");

		if(replace && !followup) {
			notify_menu.html('');
			notify_loading.hide();
		}

		$(data).each(function() {

			// do not add a notification if it is already present

			// TODO: this is questionable because at least in 'notify' notification type an item can have more than one notifications
			// e.g. one for the mention and one for the item itself.
			//if($('#nav-' + notifyType + '-menu .notification[data-b64mid=\'' + this.b64mid + '\']').length)
			//	return true;

			if(!replace && !followup && (this.thread_top && notifyType === 'network')) {
				$(document).trigger('hz:handleNetworkNotificationsItems', this);
			}

			let html = notifications_tpl.format(this.notify_link,this.photo,this.name,this.addr,this.message,this.when,this.hclass,this.b64mid,this.notify_id,this.thread_top,this.unseen,this.private_forum, encodeURIComponent(this.mids), this.body);
			notify_menu.append(html);
		});

		if(!replace && !followup) {
			$("#nav-" + notifyType + "-menu .notification").sort(function(a,b) {
				a = new Date(a.dataset.when);
				b = new Date(b.dataset.when);
				return a > b ? -1 : a < b ? 1 : 0;
			}).appendTo('#nav-' + notifyType + '-menu');
		}

		$("#nav-" + notifyType + "-menu .notifications-autotime").timeago();

		if($('#tt-' + notifyType + '-only').hasClass('active'))
			$('#nav-' + notifyType + '-menu [data-thread_top=false]').addClass('tt-filter-active');

		if($('#cn-' + notifyType + '-input').length) {
			let filter = $('#cn-' + notifyType + '-input').val().toString().toLowerCase();
			if(filter) {
				filter = filter.indexOf('%') == 0 ? filter.substring(1) : filter;

				$('#nav-' + notifyType + '-menu .notification').each(function(i, el) {
					let cn = $(el).data('contact_name').toString().toLowerCase();
					let ca = $(el).data('contact_addr').toString().toLowerCase();
					if(cn.indexOf(filter) === -1 && ca.indexOf(filter) === -1)
						$(el).addClass('cn-filter-active');
					else
						$(el).removeClass('cn-filter-active');
				});
			}
		}
	}

	function sse_updateNotifications(type, mid) {

	/*
		if(type === 'pubs')
			return true;
	*/
		if(type === 'notify' && (mid !== bParam_mid || sse_type !== 'notify'))
			return true;
	/*
		var count = Number($('.' + type + '-update').html());

		count--;

		if(count < 1) {
			$('.' + type + '-update').html(count);
			$('.' + type + '-button').fadeOut(function() {
				sse_setNotificationsStatus();
			});
		}
		else {
			$('.' + type + '-update').html(count);
		}
	*/

		$('#nav-' + type + '-menu .notification[data-b64mid=\'' + mid + '\']').fadeOut(function() {
			this.remove();
		});

	}

	function sse_setNotificationsStatus(data) {
		var primary_notifications = ['dm', 'home', 'intros', 'register', 'notify', 'files'];
		var secondary_notifications = ['network', 'forums', 'all_events', 'pubs'];
		var all_notifications = primary_notifications.concat(secondary_notifications);

		var primary_available = false;
		var any_available = false;

		all_notifications.forEach(function(type, index) {
			if($('.' + type + '-button').css('display') == 'block') {
				any_available = true;
				if(primary_notifications.indexOf(type) > -1)
					primary_available = true;
			}
		});

		if(primary_available) {
			$('.notifications-btn-icon').removeClass('bi-exclamation-circle');
			$('.notifications-btn-icon').addClass('bi-exclamation-triangle');
		}
		else {
			$('.notifications-btn-icon').removeClass('bi-exclamation-triangle');
			$('.notifications-btn-icon').addClass('bi-exclamation-circle');
		}

		if(any_available) {
			$('.notifications-btn').css('opacity', 1);
			$('#no_notifications').hide();
			$('#notifications').show();
		}
		else {
			$('.notifications-btn').css('opacity', 0.5);
			$('#navbar-collapse-1').removeClass('show');
			$('#no_notifications').show();
			$('#notifications').hide();
		}

		if (typeof data !== typeof undefined) {
			data.forEach(function(nmid, index) {

				sse_rmids.push(nmid);

				if($('.notification[data-b64mid=\'' + nmid + '\']').length) {
					$('.notification[data-b64mid=\'' + nmid + '\']').each(function() {
						var n = this.parentElement.id.split('-');
						return sse_updateNotifications(n[1], nmid);
					});
				}

				// special handling for forum notifications
				$('.notification-forum').filter(function() {
					var fmids = decodeURIComponent($(this).data('b64mids'));
					var n = this.parentElement.id.split('-');
					if(fmids.indexOf(nmid) > -1) {
						var fcount = Number($('.' + n[1] + '-update').html());
						fcount--;
						$('.' + n[1] + '-update').html(fcount);
						if(fcount < 1) {
							$('.' + n[1] + '-button').fadeOut();
							$('#nav-' + n[1] + '-sub').removeClass('show');
						}
						var count = Number($(this).find('.bg-secondary').html());
						count--;
						$(this).find('.bg-secondary').html(count);
						if(count < 1)
							$(this).remove();
					}
				});
			});
		}

	}

	function sse_fallback() {
		$.get('/sse', function(obj) {
			if(! obj)
				return;

			console.log('sse fallback');
			console.log(obj);

			sse_handleNotifications(obj, false, false);
		});
	}
</script>

 {{if !$sys_only}}
<div id="notifications_wrapper" class="mb-4">
	<div id="no_notifications" class="d-xl-none">
		{{$no_notifications}}<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span class="dot-3">.</span></span>
	</div>
	<div id="nav-notifications-template" rel="template" class="d-none">
		<a class="list-group-item list-group-item-action notification {6}" href="{0}" title="{13}" data-b64mid="{7}" data-notify_id="{8}" data-thread_top="{9}" data-contact_name="{2}" data-contact_addr="{3}" data-when="{5}">
			<img data-src="{1}" loading="lazy" class="rounded float-start me-2 menu-img-2">
			<div class="text-nowrap">
				<div class="d-flex justify-content-between align-items-center lh-sm">
					<div class="text-truncate pe-1">
						<strong title="{2} - {3}">{2}</strong>
					</div>
					<small class="notifications-autotime opacity-75" title="{5}"></small>
				</div>
				<div class="text-truncate">{4}</div>
			</div>
		</a>
	</div>
	<div id="nav-notifications-forums-template" rel="template" class="d-none">
		<a class="list-group-item list-group-item-action justify-content-between align-items-center d-flex notification notification-forum" href="{0}" title="{4} - {3}" data-b64mid="{7}" data-notify_id="{8}" data-thread_top="{9}" data-contact_name="{2}" data-contact_addr="{3}" data-b64mids='{12}'>
			<div>
				<img class="menu-img-1" data-src="{1}" loading="lazy">
				<span>{2}</span>
			</div>
			<span class="badge bg-secondary">{10}</span>
		</a>
	</div>
	<div id="notifications" class="border border-top-0 rounded navbar-nav collapse">
		{{foreach $notifications as $notification}}
		<div class="rounded-top rounded-bottom border border-start-0 border-end-0 border-bottom-0 list-group list-group-flush collapse {{$notification.type}}-button">
			<a id="notification-link-{{$notification.type}}" class="collapsed list-group-item justify-content-between align-items-center d-flex fakelink stretched-link notification-link" href="#" title="{{$notification.title}}" data-bs-target="#nav-{{$notification.type}}-sub" data-bs-toggle="collapse" data-sse_type="{{$notification.type}}">
				<div>
					<i class="bi bi-{{$notification.icon}} generic-icons-nav"></i>
					{{$notification.label}}
				</div>
				<span class="badge bg-{{$notification.severity}} {{$notification.type}}-update"></span>
			</a>
		</div>
		<div id="nav-{{$notification.type}}-sub" class="rounded-bottom border border-start-0 border-end-0 border-bottom-0 list-group list-group-flush collapse notification-content" data-bs-parent="#notifications" data-sse_type="{{$notification.type}}">
			{{if $notification.viewall}}
			<a class="list-group-item list-group-item-action text-decoration-none" id="nav-{{$notification.type}}-see-all" href="{{$notification.viewall.url}}">
				<i class="bi bi-box-arrow-up-right generic-icons-nav"></i> {{$notification.viewall.label}}
			</a>
			{{/if}}
			{{if $notification.markall}}
			<div class="list-group-item list-group-item-action cursor-pointer" id="nav-{{$notification.type}}-mark-all" onclick="markRead('{{$notification.type}}'); return false;">
				<i class="bi bi-check-circle generic-icons-nav"></i> {{$notification.markall.label}}
			</div>
			{{/if}}
			{{if $notification.filter}}
			{{if $notification.filter.posts_label}}
			<div class="list-group-item list-group-item-action cursor-pointer" id="tt-{{$notification.type}}-only">
				<i class="bi bi-funnel generic-icons-nav"></i> {{$notification.filter.posts_label}}
			</div>
			{{/if}}
			{{if $notification.filter.name_label}}
			<div class="list-group-item clearfix notifications-textinput" id="cn-{{$notification.type}}-only">
				<div class="text-muted notifications-textinput-filter"><i class="bi bi-filter"></i></div>
				<input id="cn-{{$notification.type}}-input" type="text" class="notification-filter form-control form-control-sm" placeholder="{{$notification.filter.name_label}}">
				<div id="cn-{{$notification.type}}-input-clear" class="text-muted notifications-textinput-clear d-none"><i class="bi bi-x-lg"></i></div>
			</div>
			{{/if}}
			{{/if}}
			<div id="nav-{{$notification.type}}-menu" class="list-group list-group-flush"></div>
			<div id="nav-{{$notification.type}}-loading" class="list-group-item" style="display: none;">
				{{$loading}}<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span class="dot-3">.</span></span>
			</div>
		</div>
		{{/foreach}}
	</div>
</div>
{{/if}}        

              <div class="dropdown-divider"></div> <a href="/notifications" class="dropdown-item dropdown-footer">
                  See All Notifications
              </a>
          </div>
      </li>
			{{/if}}

    {{if $userinfo}}
    <!--begin::User Menu Dropdown-->
    <li class="nav-item dropdown user-menu"> <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"> <img src="{{$userinfo.icon}}" class="user-image rounded-circle shadow" alt="User Image"> <span class="d-none d-md-inline">{{$userinfo.name}}</span> </a>
      <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end"> <!--begin::User Image-->
				{{if $is_owner}}
        <li class="user-header text-bg-secondary"> <img src="{{$userinfo.icon}}" class="bg-dark shadow" alt="User Image">
          <p>
            {{$userinfo.name}}
          </p>
        </li> <!--end::User Image--> <!--begin::Menu Body-->
        <li class="user-body p-0">
          <!--begin::Profile Row-->
          <div class="row">
            {{foreach $nav.usermenu as $usermenu}}
            <div class="col-6"><a href="{{$usermenu.0}}" class="dropdown-item">{{$usermenu.1}}</a> </div>
            {{/foreach}}
            {{if $nav.group}}
            <div class="col-6"><a href="{{$nav.group.0}}" class="dropdown-item">{{$nav.group.1}}</a>
            </div>
            {{/if}}
          </div> <!--end::Row-->
        </li>
        {{if $nav.manage}}
        <li class="user-body p-0">
          <!--begin::Channels Row-->
          <div class="row">
            <div class="col-6"><a href="{{$nav.manage.0}}" class="dropdown-item">{{$nav.manage.1}}</a>
            </div>
          </div> <!--end::Row-->
        </li>
        {{/if}}
        {{if $nav.channels}}
        <li class="user-body p-0">
          <!--begin::Channel list Row-->
          <div class="row">
            {{foreach $nav.channels as $chan}}
            <div class="col-12"><a href="manage/{{$chan.channel_id}}" class="dropdown-item">
              <i class="bi bi-circle{{if $localuser == $chan.channel_id}}-fill text-success{{else}} text-disabled{{/if}}"></i> {{$chan.channel_name}}
            </a></div>
            {{/foreach}}
        </li>
        {{/if}}
        {{if $nav.settings}}
        <li class="user-body p-0">
        <div class="row">
          <div class="col-6">
   			    <a class="dropdown-item" href="{{$nav.settings.0}}" title="{{$nav.settings.3}}" role="menuitem" id="{{$nav.settings.4}}">{{$nav.settings.1}}</a>
          </div>
          {{if $nav.admin}}
  	  			<div class="col-6">
			    		<a class="dropdown-item" href="{{$nav.admin.0}}" title="{{$nav.admin.3}}" role="menuitem" id="{{$nav.admin.4}}">{{$nav.admin.1}}</a>
            </div>
					{{/if}}
          </div>
        </li>
				{{/if}}
        <!--end::Menu Body-->
        <!--begin::Menu Footer-->
        <li class="user-footer"> 
          <div class="row">
          {{if $nav.profiles}}
            <div class="col-6">
              <a href="{{$nav.profiles.0}}" class="dropdown-item">{{$nav.profiles.1}}</a> 
            </div>
          {{/if}}
          {{if $nav.logout}}
            <div class="col-6">
            <a href="{{$nav.logout.0}}" class="btn btn-default btn-flat">{{$nav.logout.1}}</a>
            </div>
          {{/if}}
          </div> <!--end::Row-->
        </li> <!--end::Menu Footer-->
        {{/if}}
				{{if ! $is_owner}}
        <li class="user-header text-bg-secondary"> <img src="{{$userinfo.icon}}" class="bg-dark shadow" alt="User Image">
          <p>
            {{$userinfo.name}}
          </p>
        </li> <!--end::User Image--> <!--begin::Menu Body-->
        <!--begin::Menu Footer-->
        <li class="user-footer"> 
          <a href="{{$nav.rusermenu.0}}" class="btn btn-default btn-flat">{{$nav.rusermenu.1}}</a> 
          <a href="{{$nav.rusermenu.2}}" class="btn btn-default btn-flat float-end">{{$nav.rusermenu.3}}</a>
        </li> <!--end::Menu Footer-->
        {{/if}}
      </ul>
    </li>
    {{/if}}
    <!--end::User Menu Dropdown-->
    {{if $nav.login && !$userinfo}}
      {{if $nav.loginmenu.1.4}}
      <li class="nav-item mt-1 ps-2 pe-1">
        <a class="btn btn-info btn-sm" href="#" title="{{$nav.loginmenu.1.3}}" data-bs-toggle="modal" data-bs-target="#nav-login">{{$nav.loginmenu.1.1}}</a>
      </li>
      {{else}}
      <li class="nav-item mt-1 px-1">
        <a class="btn btn-primary btn-sm" href="login" title="{{$nav.loginmenu.1.3}}">{{$nav.loginmenu.1.1}}</a>
      </li>
      {{/if}}
      {{if $nav.register}}
      <li class="nav-item mt-1 px-1">
        <a class="btn btn-success btn-sm" href="{{$nav.register.0}}" title="{{$nav.register.3}}">{{$nav.register.1}}</a>
      </li>
      {{/if}}
    {{/if}}
    </ul> <!--end::End Navbar Links-->
  </div> <!--end::Container-->
</nav>

<!--begin::Sidebar-->
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
  <!--begin::Sidebar Brand-->
  <div class="sidebar-brand">
    <!--begin::Brand Link-->
    <a href="/" class="brand-link">
      <!--begin::Brand Image-->
<!--      <img
        src="./assets/img/AdminLTELogo.png"
        alt="U"
        class="brand-image opacity-75 shadow"
      /> -->
      <!--end::Brand Image-->
      <!--begin::Brand Text-->
      <span class="brand-text fw-light">{{$banner}}</span>
      <!--end::Brand Text-->
    </a>
    <!--end::Brand Link-->
  </div>
  <!--end::Sidebar Brand-->
  <!--begin::Sidebar Wrapper-->
  <div class="sidebar-wrapper">
    <nav class="mt-2">
      <!--begin::Sidebar Menu-->
      <ul
        class="nav sidebar-menu flex-column"
        data-lte-toggle="treeview"
        role="menu"
        data-accordion="false"
      >
      <li class="nav-header">
        <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
          <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked data-bs-theme-value="auto">
          <label class="btn btn-sm btn-outline-secondary" for="btnradio1"><i class="bi bi-circle-half me-2"></i>Auto</label>

          <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off" data-bs-theme-value="dark">
          <label class="btn btn-sm btn-outline-secondary" for="btnradio2"><i class="bi bi-moon-fill me-2"></i>Dark</label>

          <input type="radio" class="btn-check" name="btnradio" id="btnradio3" autocomplete="off" data-bs-theme-value="light">
          <label class="btn btn-sm btn-outline-secondary" for="btnradio3"><i class="bi bi-sun-fill me-2"></i>Light</label>
        </div>        
      </li>

        <!-- Pinned user apps -->
        {{if $navbar_apps.0}}
        <li class="nav-header" aria-disabled="true">{{$pinned_apps}}</li>
        {{foreach $navbar_apps as $navbar_app}}
          {{$navbar_app|replace:'fa':'generic-icons-nav fa'}}
        {{/foreach}}
        {{/if}}

        <!-- Channel apps; needs fixing -->
        {{if $channel_apps.0}}
        <li class="nav-header" aria-disabled="true">{{$channelapps}}</li>
        {{foreach $channel_apps as $channel_app}}
          {{$channel_app}} <br>
        {{/foreach}}
        {{/if}}

        {{if $is_owner}}
        <!-- Starred user apps -->
        <li class="nav-item"> 
        <a href="#" class="nav-link"> <i class="nav-icon bi bi-star"></i>
          <p>{{$featured_apps}}<i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview" style="display: none; box-sizing: border-box;">
        {{foreach $nav_apps as $nav_app}}
          {{$nav_app}}
        {{/foreach}}
        </ul>
        </li>        
        <li class="nav-header"><a class="nav-link" href="/apps"><i class="bi bi-gear"></i><p>{{$addapps}}</p></a></li>
        {{else}}
        <li class="nav-header" aria-disabled="true">{{$sysapps}}</li>
        <!-- System apps -->
        {{foreach $nav_apps as $nav_app}}
          {{$nav_app}}
        {{/foreach}}
        {{/if}} 
      </ul>
      <!--end::Sidebar Menu-->
    </nav>
  </div>
  <!--end::Sidebar Wrapper-->
</aside>
<!--end::Sidebar-->

