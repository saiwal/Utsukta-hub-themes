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


