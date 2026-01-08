<script>
	var sse_bs_active = false;
	var sse_offset = 0;
	var sse_type;
	var sse_partial_result = false;
	var sse_rmids = [];
	var sse_fallback_interval;
	var sse_sys_only = {{$sys_only}};

	document.addEventListener("DOMContentLoaded", function() {
		let notificationsWrapper = document.getElementById('notifications_wrapper');
		let notificationsParent = notificationsWrapper ? notificationsWrapper.parentElement.id : null;
		let notificationsBtn = document.querySelectorAll('.notifications-btn');

		// Event listener for notifications button
		if (notificationsBtn) {
			notificationsBtn.forEach(function (element) {
				element.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();

					// Remove the 'd-none' class to show the notifications wrapper
					notificationsWrapper.classList.remove('d-none');

					// Check if the notifications wrapper has the 'fs' class
					if (notificationsWrapper.classList.contains('fs')) {
						// Prepend the notifications wrapper back to its original parent and hide it
						document.getElementById(notificationsParent).appendChild(notificationsWrapper);
						notificationsWrapper.classList.add('d-none');
					} else {
						// Otherwise, prepend the notifications wrapper to 'main'
						document.querySelector('main').prepend(notificationsWrapper);
					}

					// Toggle the 'fs' class
					notificationsWrapper.classList.toggle('fs');
				});
			});
		}

		// Event listener for clicking a notification
		document.addEventListener('click', function(event) {
			if (event.target.closest('a') && event.target.closest('a').classList.contains('notification')) {
				if (notificationsWrapper.classList.contains('fs')) {
					// Move notifications wrapper back to its original parent and hide it
					notificationsWrapper.classList.remove('fs');
					notificationsWrapper.classList.add('d-none');
					document.getElementById(notificationsParent).appendChild(notificationsWrapper);

				}
			}
		});

		if(sse_enabled) {
			if(typeof(window.SharedWorker) === 'undefined') {
				// notifications with multiple tabs open will not work very well in this scenario
				let evtSource = new EventSource('/sse');

				evtSource.addEventListener('notifications', function(e) {
					let obj = JSON.parse(e.data);
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
				let myWorker = new SharedWorker('/view/js/sse_worker.js', localUser);

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
			if (!document.hidden) {
				sse_fallback();
				sse_fallback_interval = setInterval(sse_fallback, updateInterval);
			}

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

		document.querySelectorAll('.notification-link').forEach(function (element) {
			element.addEventListener('click', function (element) {
				sse_bs_notifications(element, true, false);
			});
		});

		document.querySelectorAll('.notification-filter').forEach(function (element) {
			element.addEventListener('keypress', function(e) {
				if (e.which == 13) { // Enter key
					this.blur();
					sse_offset = 0;

					// Clear the content of the menu
					document.getElementById("nav-" + sse_type + "-menu").innerHTML = '';

					// Show the loading element
					document.getElementById("nav-" + sse_type + "-loading").style.display = 'block';

					// Get the value from the input element
					var cn_val = document.getElementById('cn-' + sse_type + '-input') ? document.getElementById('cn-' + sse_type + '-input').value.toString().toLowerCase() : '';

					// Send a GET request using the Fetch API
					fetch('/sse_bs/' + sse_type + '/' + sse_offset + '?nquery=' + encodeURIComponent(cn_val))
						.then(response => response.json())
						.then(obj => {
							console.log('sse: bootstraping ' + sse_type);
							console.log(obj);

							sse_bs_active = false;
							sse_partial_result = true;
							sse_offset = obj[sse_type].offset;

							if (sse_offset < 0) {
								document.getElementById("nav-" + sse_type + "-loading").style.display = 'none';
							}

							sse_handleNotifications(obj, true, false);
						})
						.catch(error => {
							console.error('Error fetching data:', error);
						});
				}
			});
		});

		document.querySelectorAll('.notifications-textinput-clear').forEach(function (element) {
			element.addEventListener('click', function(e) {
				if (!sse_partial_result) return;

				// Clear the content of the menu
				document.getElementById("nav-" + sse_type + "-menu").innerHTML = '';

				// Show the loading element
				document.getElementById("nav-" + sse_type + "-loading").style.display = 'block';

				// Send a GET request using the Fetch API
				fetch('/sse_bs/' + sse_type)
					.then(response => response.json())
					.then(obj => {
						console.log('sse: bootstraping ' + sse_type);
						console.log(obj);

						sse_bs_active = false;
						sse_partial_result = false;
						sse_offset = obj[sse_type].offset;
						if (sse_offset < 0) {
							document.getElementById("nav-" + sse_type + "-loading").style.display = 'none';
						}

						sse_handleNotifications(obj, true, false);
					})
					.catch(error => {
						console.error('Error fetching data:', error);
					});
			});
		});

		document.querySelectorAll('.notification-content').forEach(function(element) {
			element.addEventListener('scroll', function() {
				if (this.scrollTop > this.scrollHeight - this.clientHeight - (this.scrollHeight / 7)) {
					sse_bs_notifications(sse_type, false, true);
				}
			});
		});

		{{foreach $notifications as $notification}}
		{{if $notification.filter}}

		document.querySelectorAll('#tt-{{$notification.type}}-only').forEach(function (element) {
			element.addEventListener('click', function(e) {

				let element = e.target.closest('div');
				let menu = document.querySelector('#nav-{{$notification.type}}-menu');
				let notifications = menu.querySelectorAll('.notification[data-thread_top="false"]');

				if (element.classList.contains('active') && element.classList.contains('sticky-top')) {
					notifications.forEach(function(notification) {
						notification.classList.remove('tt-filter-active');
					});
					element.classList.remove('active', 'sticky-top');
				} else {
					notifications.forEach(function(notification) {
						notification.classList.add('tt-filter-active');
					});
					element.classList.add('active', 'sticky-top');

					// Count the visible notifications
					let visibleNotifications = menu.querySelectorAll('.notification:not(.tt-filter-active):not(.cn-filter-active)').length;

					// Load more notifications if the visible count is low
					if (sse_type && sse_offset !== -1 && visibleNotifications < 15) {
						sse_bs_notifications(sse_type, false, true);
					}
				}

			});
		});

		document.querySelectorAll('#cn-{{$notification.type}}-input-clear').forEach(function (element) {
			element.addEventListener('click', function(e) {
				let input = document.getElementById('cn-{{$notification.type}}-input');
				input.value = '';

				// Remove 'active' and 'sticky-top' classes to the 'only' element
				let onlyElement = document.getElementById('cn-{{$notification.type}}-only');
				onlyElement.classList.remove('active', 'sticky-top');

				// Add 'd-none' class from the clear button
				let clearButton = document.getElementById('cn-{{$notification.type}}-input-clear');
				clearButton.classList.add('d-none');

				// Remove the 'cn-filter-active' class from all notifications
				let notifications = document.querySelectorAll("#nav-{{$notification.type}}-menu .notification");
				notifications.forEach(function(notification) {
					notification.classList.remove('cn-filter-active');
				});
			});
		});

		document.querySelectorAll('#cn-{{$notification.type}}-input').forEach(function (element) {
			element.addEventListener('input', function(e) {
				let input = e.target;
				let val = input.value.toString().toLowerCase();

				// Check if there is input value
				if (val) {
					// Remove '%' if it's at the beginning of the input value
					val = val.indexOf('%') === 0 ? val.substring(1) : val;

					// Add 'active' and 'sticky-top' classes to the 'only' element
					let onlyElement = document.getElementById('cn-{{$notification.type}}-only');
					onlyElement.classList.add('active', 'sticky-top');

					// Remove 'd-none' class from the clear button
					let clearButton = document.getElementById('cn-{{$notification.type}}-input-clear');
					clearButton.classList.remove('d-none');
				} else {
					// Remove 'active' and 'sticky-top' classes from the 'only' element
					let onlyElement = document.getElementById('cn-{{$notification.type}}-only');
					onlyElement.classList.remove('active', 'sticky-top');

					// Add 'd-none' class to the clear button
					let clearButton = document.getElementById('cn-{{$notification.type}}-input-clear');
					clearButton.classList.add('d-none');
				}

				// Loop through each notification and apply filter logic
				let notifications = document.querySelectorAll("#nav-{{$notification.type}}-menu .notification");
				notifications.forEach(function(el) {
					let cn = el.dataset.contact_name.toString().toLowerCase();
					let ca = el.dataset.contact_addr.toString().toLowerCase();

					// Check if the contact name or address matches the input value
					if (cn.indexOf(val) === -1 && ca.indexOf(val) === -1) {
						el.classList.add('cn-filter-active');
					} else {
						el.classList.remove('cn-filter-active');
					}
				});
			});
		});

		{{/if}}
		{{/foreach}}

	});

	document.addEventListener('hz:sse_setNotificationsStatus', function(e) {
		sse_setNotificationsStatus(e.detail);
	});

	document.addEventListener('hz:sse_bs_init', function() {
		sse_bs_init();
	});

	document.addEventListener('hz:sse_bs_counts', function() {
		sse_bs_counts();
	});


	function sse_bs_init() {
		// Check if 'notification_open' exists in sessionStorage or if sse_type is defined
		if (sessionStorage.getItem('notification_open') !== null || typeof sse_type !== 'undefined') {
			if (typeof sse_type === 'undefined') {
				sse_type = sessionStorage.getItem('notification_open');
			}

			// Add the 'show' class to the appropriate element
			let subNav = document.getElementById("nav-" + sse_type + "-sub");
			if (subNav) {
				subNav.classList.add('show');
			}

			// Call the sse_bs_notifications function
			sse_bs_notifications(sse_type, true, false);
		} else {
			// Call the sse_bs_counts function if conditions are not met
			sse_bs_counts();
		}
	}

	function sse_bs_counts() {
		if (sse_bs_active || sse_sys_only) {
			return;
		}

		sse_bs_active = true;

		// Use the fetch API to send the POST request with the data
		fetch('/sse_bs', {
			method: 'POST',
			body: new URLSearchParams({sse_rmids: sse_rmids})
		})
		.then(response => response.json())  // Parse the JSON response
		.then(obj => {
			console.log(obj);
			sse_bs_active = false;
			sse_rmids = [];
			sse_handleNotifications(obj, true, false);
		})
		.catch(error => {
			console.error('Error:', error);
			sse_bs_active = false;
		});
	}

	function sse_bs_notifications(e, replace, followup) {
		if (sse_bs_active || sse_sys_only) {
			return;
		}

		let manual = false;

		if (typeof replace === 'undefined') {
			replace = e.data.replace;
		}

		if (typeof followup === 'undefined') {
			followup = e.data.followup;
		}

		if (typeof e === 'string') {
			sse_type = e;
		} else {
			manual = true;
			sse_offset = 0;
			sse_type = e.target.dataset.sse_type;
		}

		if (typeof sse_type === 'undefined') {
			return;
		}

		if (followup || !manual || !document.getElementById('notification-link-' + sse_type).classList.contains('collapsed')) {

			if (sse_offset >= 0) {
				document.getElementById("nav-" + sse_type + "-loading").style.display = 'block';
			}

			sessionStorage.setItem('notification_open', sse_type);

			if (sse_offset !== -1 || replace) {
				let cn_val = (document.getElementById('cn-' + sse_type + '-input') && sse_partial_result)
					? document.getElementById('cn-' + sse_type + '-input').value.toString().toLowerCase()
					: '';

				document.getElementById("nav-" + sse_type + "-loading").style.display = 'block';

				sse_bs_active = true;

				// Send POST request using fetch API
				fetch('/sse_bs/' + sse_type + '/' + sse_offset, {
					method: 'POST',
					body: new URLSearchParams({
						sse_rmids: sse_rmids,
						nquery: encodeURIComponent(cn_val)
					})
				})
				.then(response => response.json())  // Parse the JSON response
				.then(obj => {
					console.log('sse: bootstraping ' + sse_type);
					console.log(obj);
					sse_bs_active = false;
					sse_rmids = [];
					document.getElementById("nav-" + sse_type + "-loading").style.display = 'none';

					if (typeof obj[sse_type] !== 'undefined') {
						sse_offset = obj[sse_type].offset;
					}

					sse_handleNotifications(obj, replace, followup);
				})
				.catch(error => {
					console.error('Error:', error);
					sse_bs_active = false;
				});
			} else {
				document.getElementById("nav-" + sse_type + "-loading").style.display = 'none';
			}
		} else {
			sessionStorage.removeItem('notification_open');
		}
	}

	function sse_handleNotifications(obj, replace, followup) {

		// Notice and info notifications
		if (obj.notice) {
			obj.notice.notifications.forEach(notification => {
				toast(notification, 'danger');
			});
		}

		if (obj.info) {
			obj.info.notifications.forEach(notification => {
				toast(notification, 'info');
			});
		}

		if (sse_sys_only) {
			return;
		}

		let	all_notifications = Object.keys(obj);

		all_notifications.forEach(type => {
			if (typeof obj[type] === 'undefined') {
				return;
			}

			let count = Number(obj[type].count);

			// Show notifications and update count
			let updateElement = document.querySelector('.' + type + '-update');
			let buttonElement = document.querySelector('.' + type + '-button');
			let subElement = document.getElementById('nav-' + type + '-sub');

			if (count) {
				if (buttonElement) buttonElement.style.display = 'block';  // Fade-in effect replaced by display block
				if (replace || followup) {
					updateElement.textContent = count >= {{$count_limit}} ? '{{$count_limit - 1}}+' : count;
				} else {
					count = count + Number(updateElement.textContent.replace(/\++$/, ''));
					updateElement.textContent = count >= {{$count_limit}} ? '{{$count_limit - 1}}+' : count;
				}
			} else {
				if (updateElement) updateElement.textContent = '0';
				if (subElement) subElement.classList.remove('show');
				if (buttonElement) {
					buttonElement.style.display = 'none'; // Fade-out effect replaced by display none
					sse_setNotificationsStatus(null);
				}
			}

			if (obj[type].notifications.length) {
				sse_handleNotificationsItems(type, obj[type].notifications, replace, followup);
			}
		});

		sse_setNotificationsStatus(null);

		if (typeof obj[sse_type] !== 'undefined') {
			// Load more notifications if visible notifications count becomes low
			if (sse_type && sse_offset !== -1) {
				let menu = document.getElementById('nav-' + sse_type + '-menu');
				if (menu && menu.querySelectorAll('.notification:not(.tt-filter-active):not(.cn-filter-active)').length < 15) {
					sse_bs_notifications(sse_type, false, true);
				}
			}
		}
	}

	function sse_handleNotificationsItems(notifyType, data, replace, followup) {
		let notifications_tpl = decodeURIComponent(document.querySelector("#nav-notifications-template[rel=template]").innerHTML.replace('data-src', 'src'));
		let notify_menu = document.getElementById("nav-" + notifyType + "-menu");
		let notify_loading = document.getElementById("nav-" + notifyType + "-loading");
		let notify_count = document.getElementsByClassName(notifyType + "-update");

		if (notify_menu === null) {
			return;
		}

		{{if $invert_notifications_order}}
		if (!replace && !followup && notify_menu.querySelectorAll('.notification:not(.tt-filter-active):not(.cn-filter-active)').length >= 30) {
			return;
		}
		{{/if}}

		if (replace && !followup) {
			notify_menu.innerHTML = '';  // Clear menu
			notify_loading.style.display = 'none';  // Hide loading
		}

		data.forEach(notification => {
			// Special handling for network notifications
			if (!replace && !followup && notification.thread_top && notifyType === 'network') {
				document.dispatchEvent(new CustomEvent('hz:handleNetworkNotificationsItems', { detail: notification }));
			}

			// Prepare HTML using the template
			let html = notifications_tpl.format(
				notification.notify_link,
				notification.photo,
				notification.name,
				notification.addr,
				notification.message,
				notification.when,
				notification.hclass,
				notification.b64mid,
				notification.notify_id,
				notification.thread_top,
				notification.unseen,
				notification.private_forum,
				encodeURIComponent(notification.mids),
				notification.body
			);

			// Append the new notification HTML to the menu
			notify_menu.insertAdjacentHTML('beforeend', html);
		});

		// Sort notifications by date
		if (!replace && !followup) {
			let notifications = Array.from(notify_menu.getElementsByClassName('notification'));

			notifications.sort((a, b) => {
				let dateA = new Date(a.dataset.when).getTime();
				let dateB = new Date(b.dataset.when).getTime();

				{{if $invert_notifications_order}}
				return dateA - dateB; // Sort in ascending order
				{{else}}
				return dateB - dateA; // Sort in descending order
				{{/if}}
			});

			notifications.forEach(notification => notify_menu.appendChild(notification));
		}


		// Filter thread_top notifications if the filter is active
		let filterThreadTop = document.getElementById('tt-' + notifyType + '-only');
		if (filterThreadTop && filterThreadTop.classList.contains('active')) {
			let notifications = notify_menu.querySelectorAll('[data-thread_top="false"]');
			notifications.forEach(notification => notification.classList.add('tt-filter-active'));
		}

		// Filter notifications based on the input field
		let filterInput = document.getElementById('cn-' + notifyType + '-input');
		if (filterInput) {
			let filter = filterInput.value.toString().toLowerCase();
			if (filter) {
				if (filter.indexOf('%') === 0) filter = filter.substring(1);  // Remove the percent if it exists
				let notifications = notify_menu.querySelectorAll('.notification');
				notifications.forEach(notification => {
					let cn = notification.dataset.contact_name.toString().toLowerCase();
					let ca = notification.dataset.contact_addr.toString().toLowerCase();
					if (cn.indexOf(filter) === -1 && ca.indexOf(filter) === -1) {
						notification.classList.add('cn-filter-active');
					} else {
						notification.classList.remove('cn-filter-active');
					}
				});
			}
		}

		// Update relative time for notifications
		updateRelativeTime('.autotime-narrow');
	}


	function sse_updateNotifications(mid) {
		// Find the notification elements based on its 'data-b64mid' or href attribute.
		// The latter will match reactions where b64mid will contain the uuid of its thread parent (reacted on) instead of its own.
		let notifications = document.querySelectorAll(`.notification[data-b64mid='${mid}'], .notification[href*='display/${mid}']`);

		notifications.forEach(notification => {
			let type = notification.parentElement.id.split('-')[1];

			// Skip processing if the type is 'notify' and the conditions don't match
			if (type === 'notify' && (mid !== bParam_mid || sse_type !== 'notify')) {
				return true;
			}

			notification.remove();
		});
	}


	function sse_setNotificationsStatus(data) {
		let primary_notifications = ['dm', 'home', 'intros', 'register', 'notify', 'files'];
		let nlinks = document.getElementById('notifications').querySelectorAll('.notification-link');
		let primary_available = false;
		let any_available = false;

		// Loop through all notifications and check their visibility
		nlinks.forEach(nlink => {
			let type = nlink.dataset.sse_type;
			let button = document.querySelector(`.${type}-button`);
			if (button && getComputedStyle(button).display === 'block') {
				any_available = true;
				if (primary_notifications.indexOf(type) > -1) {
					primary_available = true;
				}
			}
		});

		// Update notification button icons based on the primary notification availability
		let notificationIcons = document.querySelectorAll('.notifications-btn-icon');

		if (notificationIcons) {
			let iconClass = primary_available ? 'bi-exclamation-triangle' : 'bi-exclamation-circle';
			let iconToRemove = primary_available ? 'bi-exclamation-circle' : 'bi-exclamation-triangle';

			notificationIcons.forEach(notificationIcon => {
				notificationIcon.classList.replace(iconToRemove, iconClass);
			});
		}

		// Update visibility of notification button and sections
		let notificationsBtn = document.querySelectorAll('.notifications-btn');
		let noNotifications = document.querySelector('#no_notifications');
		let notifications = document.querySelector('#notifications');
		let navbarCollapse = document.querySelector('#navbar-collapse-1');

		if (any_available) {
			notificationsBtn.forEach(btn => {
				btn.style.opacity = 1;
			});
			noNotifications.style.display = 'none';
			notifications.style.display = 'block';
		} else {
			if (notificationsBtn) {
				notificationsBtn.forEach(btn => {
					btn.style.opacity = 0.5;
				});
			}
			if (navbarCollapse) navbarCollapse.classList.remove('show');
			noNotifications.style.display = 'block';
			notifications.style.display = 'none';
		}

		// Handle specific notifications if 'data' is provided
		if (data) {
			data.forEach(function (nmid) {
				sse_rmids.push(nmid);
				sse_updateNotifications(nmid);
			});
		}
	}

	function sse_fallback() {
		fetch('/sse')
			.then(response => response.json())
			.then(obj => {
				if (!obj) return;

				console.log('sse fallback');
				console.log(obj);

				sse_handleNotifications(obj, false, false);
			})
			.catch(error => {
				console.error('Error fetching SSE data:', error);
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
					<small class="autotime-narrow text-body-secondary" title="{5}"></small>
				</div>
				<div class="text-truncate">{4}</div>
			</div>
		</a>
	</div>
	<div id="notifications" class="collapse">
		<div class="">
		{{foreach $notifications as $notification}}
		<div class="{{$notification.type}}-button">
			<a id="notification-link-{{$notification.type}}" class="lcars-text-bar collapsed notification-link" href="#" title="{{$notification.title}}" data-bs-target="#nav-{{$notification.type}}-sub" data-bs-toggle="collapse" data-sse_type="{{$notification.type}}">
				<span>
					<i class="bi bi-{{$notification.icon}} pe-1 ps-4"></i>
					{{$notification.label}}
				</span>
				<span class="blink text-{{$notification.severity}} the-end {{$notification.type}}-update"></span>
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
</div>
{{/if}}
