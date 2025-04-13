<nav class="app-header navbar navbar-expand  bg-body-tertiary border-0 sticky-top"> <!--begin::Container-->
  <div class="container-fluid"> <!--begin::Start Navbar Links-->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-layout-sidebar"></i></a>
      </li>
    </ul>
    <ul class="navbar-nav">
      {{if $userinfo}}
      {{if $sel.name}}
      {{if $sitelocation}}
      <div class="lh-1 d-flex flex-column align-items-center">
        <h1 class="h6 mb-2 lh-1">{{$sel.name}}</h1>
        <small>{{$sitelocation}}</small>
      </div>
      {{else}}
      <li class="nav-item">
        <a class="nav-link active" aria-current="page" href="{{$url}}">{{$sel.name}}</a>
      </li>
      {{/if}}
      {{/if}}
      {{/if}}
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto align-items-center justify-content-center">
      <!-- App settings icon-->
      {{if $userinfo}}
      {{if $sel.name}}
      {{if $settings_url}}
      <li class="nav-item">
        <a href="{{$settings_url}}/?f=&rpath={{$url}}" class="nav-link"><i class="bi bi-gear"></i></a>
      </li>
      {{/if}}
      {{/if}}
      {{/if}}

      <!-- Navbar Search -->
      <li class="nav-item">
        <a class="nav-link d-md-none" href="/search"><i class="bi bi-search"></i></a>
        <div class="navbar-search-block d-none d-md-block">
          <form class="form-inline" method="get" action="{{$nav.search.4}}" role="search">
            <input class="form-control me-sm-2" id="nav-search-text" type="text" value=""
              placeholder="{{$help}}" name="search" title="{{$nav.search.3}}" onclick="this.submit();"
              onblur="closeMenu('nav-search'); openMenu('nav-search-btn');" />
          </form>
        </div>
      </li>

 			{{if $localuser || $nav.pubs}}
      <li class="nav-item">
		  <button id="notifications-btn-1" type="button" class="navbar-toggler border-0 notifications-btn">
				<i id="notifications-btn-icon-1" class="bi bi-exclamation-circle notifications-btn-icon generic-icons"></i>
			</button>
      </li>
			{{/if}}
     
      <!--Notification icon-->
      <li class="nav-item dropdown">
        <a id="notifications-btn-1" class="nav-link show notifications-btn" data-bs-toggle="dropdown" href="#" aria-expanded="true">
          <i id="notifications-btn-icon-1" class="bi bi-bell notifications-btn-icon"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" data-bs-popper="static">
          {{if !$sys_only}}
          <div id="notifications_wrapper" class=" ">
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
                    <small class="autotime-narrow opacity-75" title="{5}"></small>
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
            <div id="notifications" class=" ">
              {{foreach $notifications as $notification}}
              <div class="dropdown-item collapse {{$notification.type}}-button">
                <a id="notification-link-{{$notification.type}}" class="collapsed list-group-item justify-content-between align-items-center d-flex fakelink stretched-link notification-link" href="#" title="{{$notification.title}}" data-bs-target="#nav-{{$notification.type}}-sub" data-bs-toggle="collapse" data-sse_type="{{$notification.type}}">
                  <div>
                    <i class="bi bi-{{$notification.icon}} generic-icons-nav"></i>
                    {{$notification.label}}
                  </div>
                  <span class="badge bg-{{$notification.severity}} {{$notification.type}}-update"></span>
                </a>
              </div>
              <div id="nav-{{$notification.type}}-sub" class="border border-start-0 border-end-0 border-bottom-0 list-group list-group-flush collapse notification-content" data-bs-parent="#notifications" data-sse_type="{{$notification.type}}">
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
        </div>
      </li>
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
		let notificationsBtn = document.querySelector('.notifications-btn');

		// Event listener for notifications button
		if (notificationsBtn) {
			notificationsBtn.addEventListener('click', function() {
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
		}

		// Event listener for clicking a notification
		document.addEventListener('click', function(event) {
			if (event.target.closest('a') && event.target.closest('a').classList.contains('notification')) {
				console.log(1)
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

				// Function to check if an element is visible
				function isVisible(el) {
					return el.offsetWidth > 0 && el.offsetHeight > 0;
				}

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
					let visibleNotifications = Array.from(menu.querySelectorAll('.notification')).filter(isVisible).length;

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
					sse_offset = obj[sse_type].offset;
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

		let primary_notifications = ['dm', 'home', 'intros', 'register', 'notify', 'files'];
		let secondary_notifications = ['network', 'forums', 'all_events', 'pubs'];
		let all_notifications = [...primary_notifications, ...secondary_notifications];

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
					updateElement.textContent = count >= 100 ? '99+' : count;
				} else {
					count = count + Number(updateElement.textContent.replace(/\++$/, ''));
					updateElement.textContent = count >= 100 ? '99+' : count;
				}
			} else {
				if (updateElement) updateElement.textContent = '0';
				if (subElement) subElement.classList.remove('show');
				if (buttonElement) {
					buttonElement.style.display = 'none'; // Fade-out effect replaced by display none
					sse_setNotificationsStatus();
				}
			}

			if (obj[type].notifications.length) {
				sse_handleNotificationsItems(type, obj[type].notifications, replace, followup);
			}
		});

		sse_setNotificationsStatus();

		// Load more notifications if visible notifications count becomes low
		if (sse_type && sse_offset !== -1) {
			let menu = document.getElementById('nav-' + sse_type + '-menu');
			if (menu && menu.children.length < 15) {
				sse_bs_notifications(sse_type, false, true);
			}
		}
	}

	function sse_handleNotificationsItems(notifyType, data, replace, followup) {

		// Get the template, adjust based on the notification type
		let notifications_tpl = (notifyType === 'forums')
			? decodeURIComponent(document.querySelector("#nav-notifications-forums-template[rel=template]").innerHTML.replace('data-src', 'src'))
			: decodeURIComponent(document.querySelector("#nav-notifications-template[rel=template]").innerHTML.replace('data-src', 'src'));

		let notify_menu = document.getElementById("nav-" + notifyType + "-menu");
		let notify_loading = document.getElementById("nav-" + notifyType + "-loading");
		let notify_count = document.getElementsByClassName(notifyType + "-update");

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
				let dateA = new Date(a.dataset.when);
				let dateB = new Date(b.dataset.when);
				return dateA > dateB ? -1 : dateA < dateB ? 1 : 0;
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


	function sse_updateNotifications(type, mid) {

		// Skip processing if the type is 'notify' and the conditions don't match
		if (type === 'notify' && (mid !== bParam_mid || sse_type !== 'notify')) {
			return true;
		}

		// Find the notification element based on its 'data-b64mid' attribute
		let notification = document.querySelector(`#nav-${type}-menu .notification[data-b64mid='${mid}']`);

		if (notification) {
			notification.remove();
		}
	}


	function sse_setNotificationsStatus(data) {
		let primary_notifications = ['dm', 'home', 'intros', 'register', 'notify', 'files'];
		let secondary_notifications = ['network', 'forums', 'all_events', 'pubs'];
		let all_notifications = primary_notifications.concat(secondary_notifications);

		let primary_available = false;
		let any_available = false;

		// Loop through all notifications and check their visibility
		all_notifications.forEach(function (type) {
			let button = document.querySelector(`.${type}-button`);
			if (button && getComputedStyle(button).display === 'block') {
				any_available = true;
				if (primary_notifications.indexOf(type) > -1) {
					primary_available = true;
				}
			}
		});

		// Update notification button icon based on the primary notification availability
		let notificationIcon = document.querySelector('.notifications-btn-icon');
		if (primary_available) {
			notificationIcon.classList.remove('bi-exclamation-circle');
			notificationIcon.classList.add('bi-exclamation-triangle');
		} else {
			notificationIcon.classList.remove('bi-exclamation-triangle');
			notificationIcon.classList.add('bi-exclamation-circle');
		}

		// Update visibility of notification button and sections
		let notificationsBtn = document.querySelector('.notifications-btn');
		let noNotifications = document.querySelector('#no_notifications');
		let notifications = document.querySelector('#notifications');
		let navbarCollapse = document.querySelector('#navbar-collapse-1');

		if (any_available) {
			notificationsBtn.style.opacity = 1;
			noNotifications.style.display = 'none';
			notifications.style.display = 'block';
		} else {
			notificationsBtn.style.opacity = 0.5;
			if (navbarCollapse) navbarCollapse.classList.remove('show');
			noNotifications.style.display = 'block';
			notifications.style.display = 'none';
		}

		// Handle specific notifications if 'data' is provided
		if (typeof data !== 'undefined') {
			data.forEach(function (nmid) {
				sse_rmids.push(nmid);

				// Handle regular notifications
				let notification = document.querySelector(`.notification[data-b64mid='${nmid}']`);
				if (notification) {
					let parentId = notification.parentElement.id.split('-')[1];
					sse_updateNotifications(parentId, nmid);
				}

				// Special handling for forum notifications
				let forumNotifications = document.querySelectorAll('.notification-forum');
				forumNotifications.forEach(function (forumNotification) {
					let fmids = decodeURIComponent(forumNotification.dataset.b64mids);
					let parentId = forumNotification.parentElement.id.split('-')[1];

					if (fmids.indexOf(nmid) > -1) {
						let updateElem = document.querySelector(`.${parentId}-update`);
						let fcount = Number(updateElem.innerText);
						fcount--;
						updateElem.innerText = fcount;

						if (fcount < 1) {
							let button = document.querySelector(`.${parentId}-button`);
							button.style.display = 'none';
							let subMenu = document.querySelector(`#nav-${parentId}-sub`);
							if (subMenu) subMenu.classList.remove('show');
						}

						let countElem = forumNotification.querySelector('.bg-secondary');
						let count = Number(countElem.innerText);
						count--;
						countElem.innerText = count;

						if (count < 1) {
							forumNotification.remove();
						}
					}
				});
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

      <!-- user dowpdown menu-->
      {{if $userinfo}}
      <!--begin::User Menu Dropdown-->
      <li class="nav-item dropdown user-menu"> <a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle ps-2" data-bs-toggle="dropdown">
          <img src="{{$userinfo.icon}}" class="rounded-circle shadow img-size-32" alt="User Image"></a>

        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end" style="overflow-y: auto; overflow-x:hidden; max-height: 80vh;"> <!--begin::User Image-->
          {{if $is_owner}}
          <!--begin::Menu Body-->
          <li class="user-body p-0">
            <!--begin::Profile Row-->
            <div class="row">
              {{foreach $nav.usermenu as $usermenu}}
              <div class="col-12"><a href="{{$usermenu.0}}" class="dropdown-item">{{$usermenu.1}}</a> </div>
              {{/foreach}}
              {{if $nav.group}}
              <div class="col-12"><a href="{{$nav.group.0}}" class="dropdown-item">{{$nav.group.1}}</a>
              </div>
              {{/if}}
            </div> <!--end::Row-->
          </li>
          {{if $nav.manage}}
          <li class="user-body p-0">
            <!--begin::Channels Row-->
            <div class="row">
              <div class="col-12"><a href="{{$nav.manage.0}}" class="dropdown-item">{{$nav.manage.1}}</a>
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
                  <i
                    class="bi bi-circle{{if $localuser == $chan.channel_id}}-fill text-success{{else}} text-disabled{{/if}}"></i>
                  {{$chan.channel_name}}
                </a></div>
              {{/foreach}}
          </li>
          {{/if}}
          {{if $nav.settings}}
          <li class="user-body p-0">
            <div class="row">
              <div class="col-12">
                <a class="dropdown-item" href="{{$nav.settings.0}}" title="{{$nav.settings.3}}" role="menuitem"
                  id="{{$nav.settings.4}}">{{$nav.settings.1}}</a>
              </div>
              {{if $nav.admin}}
              <div class="col-12">
                <a class="dropdown-item" href="{{$nav.admin.0}}" title="{{$nav.admin.3}}" role="menuitem"
                  id="{{$nav.admin.4}}">{{$nav.admin.1}}</a>
              </div>
              {{/if}}
            </div>
          </li>
          {{/if}}
          <!--end::Menu Body-->
          <!--begin::Menu Footer-->
          <li class="user-body p-0">
            <div class="row">
              {{if $nav.profiles}}
              <div class="col-12">
                <a href="{{$nav.profiles.0}}" class="dropdown-item">{{$nav.profiles.1}}</a>
              </div>
              {{/if}}
              {{if $nav.logout}}
              <div class="col-12">
                <a href="{{$nav.logout.0}}" class="dropdown-item">{{$nav.logout.1}}</a>
              </div>
              {{/if}}
            </div> <!--end::Row-->
          </li> <!--end::Menu Footer-->
          {{/if}}
          {{if ! $is_owner}}
          <!--begin::Menu Footer-->
          <li class="user-footer">
            <div class="col-12">
              <a href="{{$nav.rusermenu.0}}" class="dropdown-item">{{$nav.rusermenu.1}}</a>
            </div>
            <div class="col-12">
              <a href="{{$nav.rusermenu.2}}" class="dropdown-item">{{$nav.rusermenu.3}}</a>
            </div>
          </li> <!--end::Menu Footer-->
          {{/if}}
        </ul>
      </li>
      {{/if}}
      <!--end::User Menu Dropdown-->
      {{if $nav.login && !$userinfo}}
      {{if $nav.loginmenu.1.4}}
      <li class="nav-item ps-2 pe-1">
        <a class="btn btn-info" href="#" title="{{$nav.loginmenu.1.3}}" data-bs-toggle="modal"
          data-bs-target="#nav-login">{{$nav.loginmenu.1.1}}</a>
      </li>
      {{else}}
      <li class="nav-item px-1">
        <a class="btn btn-primary" href="login" title="{{$nav.loginmenu.1.3}}">{{$nav.loginmenu.1.1}}</a>
      </li>
      {{/if}}
      {{if $nav.register}}
      <li class="nav-item px-1">
        <a class="btn btn-success" href="{{$nav.register.0}}" title="{{$nav.register.3}}">{{$nav.register.1}}</a>
      </li>
      {{/if}}
      {{/if}}

      <!-- right sidebar button on smaller screen-->
      <li class="nav-item">
        <a class="nav-link d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive"
          aria-controls="offcanvasResponsive"><i class="bi bi-layout-text-sidebar"></i></a>
      </li>

    </ul> <!--end::End Navbar Links-->
  </div> <!--end::Container-->
</nav>


<!--begin::Sidebar-->
<aside class="app-sidebar bg-dark shadow" data-bs-theme="dark">
  <!--begin::Sidebar Brand-->
  <div class="sidebar-brand border-0">
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
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
        <li class="nav-header pb-3 pt-1"><div class="d-flex justify-content-center">
          <div id="bd-theme" class="btn-group" role="group" aria-label="Basic radio toggle button group">
            <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked
              data-bs-theme-value="auto">
            <label class="btn btn-sm btn-outline-primary" for="btnradio1"><i
                class="bi bi-circle-half me-1"></i>Auto</label>

            <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off"
              data-bs-theme-value="dark">
            <label class="btn btn-sm btn-outline-primary" for="btnradio2"><i
                class="bi bi-moon-fill me-1"></i>Dark</label>

            <input type="radio" class="btn-check" name="btnradio" id="btnradio3" autocomplete="off"
              data-bs-theme-value="light">
            <label class="btn btn-sm btn-outline-primary" for="btnradio3"><i
                class="bi bi-sun-fill me-1"></i>Light</label>
          </div></div>
        </li>
        <!-- Pinned user apps -->
        {{if $navbar_apps.0}}
        <li class="nav-item menu-open">
          <a href="#" class="nav-link"> <i class="nav-icon bi bi-pin-angle-fill"></i>
            <p>{{$pinned_apps}}<i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul id="nav-app-bin-container" class="nav nav-treeview" style="display: block; box-sizing: border-box;">
            {{foreach $navbar_apps as $navbar_app}}
            {{$navbar_app|replace:'fa':'generic-icons-nav fa'}}
            {{/foreach}}
          </ul>
        </li>
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
          <a href="#" class="nav-link"> <i class="nav-icon bi bi-star-fill"></i>
            <p>{{$featured_apps}}<i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul id="app-bin-container" data-token="{{$form_security_token}}" class="nav nav-treeview"
            style="display: none; box-sizing: border-box;">
            {{foreach $nav_apps as $nav_app}}
            {{$nav_app}}
            {{/foreach}}
          </ul>
        </li>
        <li class="nav-header"><a class="nav-link" href="/apps"><i class="bi bi-plus-lg"></i>
            <p>{{$addapps}}</p>
          </a></li>
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

{{if $is_owner}}
<script>
  var nav_app_bin_container = document.getElementById('app-bin-container');
  new Sortable(nav_app_bin_container, {
    animation: 150,
    delay: 200,
    delayOnTouchOnly: true,
    onEnd: function (e) {
      let app_str = '';
      $('#app-bin-container a').each(function () {
        if (app_str.length) {
          app_str = app_str.concat(',', $(this).text());
        }
        else {
          app_str = app_str.concat($(this).text());
        }
      });
      $.post(
        'pconfig',
        {
          'aj': 1,
          'cat': 'system',
          'k': 'app_order',
          'v': app_str,
          'form_security_token': $('#app-bin-container').data('token')
        }
      );

    }
  });
  var nav_app_bin_container = document.getElementById('nav-app-bin-container');
  new Sortable(nav_app_bin_container, {
    animation: 150,
    delay: 200,
    delayOnTouchOnly: true,
    onEnd: function (e) {
      let nav_app_str = '';
      $('#nav-app-bin-container a').each(function () {
        if (nav_app_str.length) {
          nav_app_str = nav_app_str.concat(',', $(this).text());
        }
        else {
          nav_app_str = nav_app_str.concat($(this).text());
        }
      });
      $.post(
        'pconfig',
        {
          'aj': 1,
          'cat': 'system',
          'k': 'app_pin_order',
          'v': nav_app_str,
          'form_security_token': $('#app-bin-container').data('token')
        }
      );

    }
  });

  var papp, app_icon, app_url;
  $(document).on('dragstart', function (e) {
    papp = e.target.dataset.papp || null;
    app_icon = e.target.dataset.icon || null;
    app_url = e.target.dataset.url || null;
    app_name = e.target.dataset.name || null;
  });

</script>
{{/if}}
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Function to apply the saved sidebar state
  function applySavedState() {
    const isDesktop = window.innerWidth >= 768; // AdminLTE's desktop breakpoint
    const savedState = localStorage.getItem('sidebarCollapsed');

    // Apply state only on desktop
    if (isDesktop && savedState !== null) {
      document.body.classList.toggle('sidebar-collapse', savedState === 'true');
    }
  }

  // Apply saved state on initial load
  applySavedState();

  // Re-apply state when window is resized to desktop
  window.addEventListener('resize', applySavedState);

  // Watch for sidebar class changes to update localStorage
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.attributeName === 'class') {
        const isDesktop = window.innerWidth >= 768;
        const isCollapsed = document.body.classList.contains('sidebar-collapse');
        
        // Save state only for desktop interactions
        if (isDesktop) {
          localStorage.setItem('sidebarCollapsed', isCollapsed);
        }
      }
    });
  });

  // Start observing the body element for class changes
  observer.observe(document.body, { attributes: true });
});
</script>
