<div id="pinned-wrapper-{{$id}}" class="pinned-item toplevel_item generic-content-wrapper h-entry" data-b64mids='{{$mids}}'>
	<div class="wall-item-outside-wrapper" id="pinned-item-outside-wrapper-{{$id}}">
		<div class="wall-item-content-wrapper" id="pinned-item-content-wrapper-{{$id}}">
			{{if $photo}}
				<div class="wall-photo-item" id="pinned-photo-item-{{$id}}">
					{{$photo}}
				</div>
			{{/if}}
			{{if $event}}
				<div class="wall-event-item" id="pinned-event-item-{{$id}}">
					{{$event}}
				</div>
			{{/if}}
			{{if $title && !$event}}
				<div class="p-2{{if $is_new}} bg-primary text-white{{/if}} wall-item-title h3{{if !$photo}} rounded-top{{/if}}" id="pinned-item-title-{{$id}}">
					{{if $title_tosource}}
						{{if $plink}}
							<a href="{{$plink.href}}" title="{{$title}} ({{$plink.title}})" rel="nofollow">
						{{/if}}
					{{/if}}
					{{$title}}
					{{if $title_tosource}}
						{{if $plink}}
							</a>
						{{/if}}
					{{/if}}
				</div>
				{{if ! $is_new}}
				<hr class="m-0">
				{{/if}}
			{{/if}}
			<div class="p-2 wall-item-head{{if !$title && !$event && !$photo}} rounded-top{{/if}}{{if $is_new && !$event}} wall-item-head-new{{/if}}" >
				<div class="lh-sm text-end float-end">
					<div class="wall-item-ago text-body-secondary" id="pinned-item-ago-{{$id}}">
						{{if $location}}
						{{$location}}
						{{/if}}
						{{if $editedtime}}
						<i class="bi bi-pencil" title="{{$editedtime}}"></i>
						{{/if}}
						{{if $verified}}
						<i class="bi bi-shield-check" title="{{$verified}}"></i>
						{{elseif $forged}}
						<i class="bi bi-shield-exclamation text-danger" title="{{$forged}}"></i>
						{{/if}}
						{{if $no_comment}}
							<i class="bi bi-ban" title="{{$no_comment}}"></i>
						{{/if}}
						{{if $delayed}}
						<i class="bi bi-clock" title="{{$delayed}}"></i>
						{{/if}}
						{{if $expiretime}}
						<i class="bi bi-clock-history" title="{{$expiretime}}"></i>
						{{/if}}
						<small class="autotime" title="{{$isotime}}"><time class="dt-published" datetime="{{$isotime}}">{{$localtime}}</time>{{if $expiretime}}&nbsp;{{$expiretime}}{{/if}}</small>
					</div>
					{{if $pinned}}
					<div class="wall-item-pinned" title="{{$pinned}}" id="pinned-item-pinned-{{$id}}"><i class="bi bi-pin-fill"></i></div>
					{{/if}}
				</div>
				<div class="float-start wall-item-info pe-2" id="pinned-item-info-{{$id}}" >
					<div class="wall-item-photo-wrapper{{if $owner_url}} wwfrom{{/if}} h-card p-author" id="pinned-item-photo-wrapper-{{$id}}">
						{{if $item.contact_id}}
						<div class="spinner-wrapper contact-edit-rotator contact-edit-rotator-{{$contact_id}}"><div class="spinner s"></div></div>
						{{/if}}
						<img src="{{$thumb}}" class="fakelink wall-item-photo{{$sparkle}} u-photo p-name" id="pinned-item-photo-{{$id}}" alt="{{$name}}" loading="lazy" data-bs-toggle="dropdown" />
						{{if $item.author_is_group_actor}}
						<i class="bi bi-chat-quote-fill wall-item-photo-group-actor" title="{{$author_is_group_actor}}"></i>
						{{/if}}
						{{if $item.thread_author_menu}}
						<i class="bi bi-caret-down-fill wall-item-photo-caret cursor-pointer" data-bs-toggle="dropdown"></i>
						<div class="dropdown-menu">
							{{foreach $item.thread_author_menu as $mitem}}
							<a class="dropdown-item{{if $mitem.class}} {{$mitem.class}}{{/if}}" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}}{{if $mitem.data}} {{$mitem.data}}{{/if}}>{{$mitem.title}}</a>
							{{/foreach}}
						</div>
						{{/if}}
					</div>
				</div>
				<div class="wall-item-author text-truncate">
					<a href="{{$profile_url}}" title="{{$linktitle}}" class="wall-item-name-link u-url"><span class="wall-item-name" id="pinned-item-name-{{$id}}" >{{$name}}</span></a>{{if $owner_url}}&nbsp;{{$via}}&nbsp;<a href="{{$owner_url}}" title="{{$olinktitle}}" class="wall-item-name-link"><span class="wall-item-name" id="pinned-item-ownername-{{$id}}">{{$owner_name}}</span></a>{{/if}}<br>
					<small class="wall-item-addr text-body-secondary">{{$author_id}}</small>
				</div>
			</div>
			{{if $divider}}
			<hr class="wall-item-divider">
			{{/if}}
			{{if $body}}
			<div class="p-2 wall-item-content clearfix" id="pinned-item-content-{{$id}}">
				<div class="wall-item-body e-content" id="pinned-item-body-{{$id}}" >
					{{$body}}
				</div>
			</div>
			{{/if}}
			{{if $has_tags}}
			<div class="p-2 wall-item-tools clearfix">
				<div class="body-tags">
					<span class="tag">{{$mentions}} {{$tags}} {{$categories}} {{$folders}}</span>
				</div>
			</div>
			{{/if}}
			<div class="p-2 wall-item-tools d-flex justify-content-between">
				<div class="wall-item-tools-left hstack gap-1" id="pinned-item-tools-left-{{$id}}">
					{{foreach $responses as $verb=>$response}}
					<button type="button" title="{{$response.count}} {{$response.button.label}}" class="disabled btn btn-sm btn-link{{if !$observer_activity.$verb}} link-secondary{{/if}} wall-item-{{$response.button.class}}" id="pinned-item-{{$verb}}-{{$id}}">
						<i class="bi bi-{{$response.button.icon}} generic-icons"></i>{{if $response.count}}<span style="display: inline-block; margin-top: -.25rem;" class="align-top">{{$response.count}}</span>{{/if}}
					</button>
					{{/foreach}}
					<div class="">
						<div id="like-rotator-{{$id}}" class="spinner-wrapper">
							<div class="spinner s"></div>
						</div>
					</div>
				</div>
				<div class="wall-item-tools-right hstack gap-1" id="pinned-item-tools-right-{{$id}}">
					{{if $attachments}}
					<div class="">
						<button type="button" class="btn btn-sm btn-link link-secondary wall-item-attach" data-bs-toggle="dropdown" id="pinned-attachment-menu-{{$id}}"><i class="bi bi-paperclip generic-icons"></i></button>
						<div class="dropdown-menu dropdown-menu-end">{{$attachments}}</div>
					</div>
					{{/if}}
					<div class="">
						<button type="button" class="btn btn-sm btn-link link-secondary" data-bs-toggle="dropdown" id="wall-item-menu-{{$item.id}}">
							<i class="bi bi-three-dots-vertical generic-icons"></i>
						</button>
						<div class="dropdown-menu dropdown-menu-end" role="menu" aria-labelledby="wall-item-menu-{{$item.id}}">
							{{if $plink}}
							<a class="dropdown-item" href="{{$plink.href}}" title="{{$plink.title}}" class="u-url"><i class="generic-icons-nav bi bi-box-arrow-up-right"></i>{{$plink.title}}</a>
							{{/if}}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
{{if $hide}}
<script>
	function dopinhide(id) {
		id = id.toString();
		if($('#pinned-wrapper-' + id).length) {
			$('#pinned-wrapper-' + id).fadeTo('fast', 0.33, function() { this.remove(); });
			$.post('pin/hide', { 'id' : id });
		}
	}
</script>
{{/if}}
<script>
	updateRelativeTime('.autotime');
</script>
