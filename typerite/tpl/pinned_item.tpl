<article id="pinned-wrapper-{{$id}}" class="pinned-item toplevel_item generic-content-wrapper h-entry add-bottom"
	data-b64mids='{{$mids}}'>
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
			<h2 class="h1{{if $is_new}} bg-primary text-white{{/if}} {{if !$photo}} rounded-top{{/if}}"
				id="pinned-item-title-{{$id}}">
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
			</h2>
			{{if ! $is_new}}
			{{/if}}
			{{/if}}
			<ul class="entry__header-meta">
				<li class="author">
					{{if $item.author_is_group_actor}}
					<i class="bi bi-chat-quote-fill pe-2" title="{{$item.author_is_group_actor}}"></i>
					{{else}}
					<svg width="24" height="24" fill="none" viewBox="0 0 24 24">
						<circle cx="12" cy="8" r="3.25" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
							stroke-width="1.5"></circle>
						<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
							d="M6.8475 19.25H17.1525C18.2944 19.25 19.174 18.2681 18.6408 17.2584C17.8563 15.7731 16.068 14 12 14C7.93201 14 6.14367 15.7731 5.35924 17.2584C4.82597 18.2681 5.70558 19.25 6.8475 19.25Z">
						</path>
					</svg>
					{{/if}}
					<span class="text-truncate">
						<a href="{{$profile_url}}" class="lh-sm u-url" title="{{$linktitle}}" ><span
								class="wall-item-name" id="pinned-item-name-{{$id}}">{{$name}}</span></a>{{if
						$owner_url}}&nbsp;{{$via}}&nbsp;<a href="{{$owner_url}}" title="{{$olinktitle}}"
							class=""><span id="pinned-item-ownername-{{$id}}"><em>{{$owner_name}}</em></span></a>{{/if}}
					</span>
				</li>
				<li class="date">
					<svg width="24" height="24" fill="none" viewBox="0 0 24 24">
						<circle cx="12" cy="12" r="7.25" stroke="currentColor" stroke-width="1.5"></circle>
						<path stroke="currentColor" stroke-width="1.5" d="M12 8V12L14 14"></path>
					</svg>
					<span class="autotime" title="{{$isotime}}"><time class="dt-published"
							datetime="{{$isotime}}">{{$localtime}}</time>{{if
						$expiretime}}&nbsp;{{$expiretime}}{{/if}}</span>
				</li>
				<li class="cat-links">
					{{if $has_tags}}
					<div class="wall-item-tools clearfix">
						<div class="body-tags">
							<span class="tag">{{$mentions}} {{$tags}} {{$categories}} {{$folders}}</span>
						</div>
					</div>
					{{/if}}
				</li>
			</ul>
			{{if $divider}}
			<hr class="wall-item-divider">
			{{/if}}
			{{if $body}}
			<div class="wall-item-content clearfix add-bottom" id="pinned-item-content-{{$id}}">
				<div class="wall-item-body e-content" id="pinned-item-body-{{$id}}">
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
				<div class="wall-item-tools-left hstack gap-5" id="pinned-item-tools-left-{{$id}}">
					{{foreach $responses as $verb=>$response}}
					<a type="button" title="{{$response.count}} {{$response.button.label}}"
						class="link-disabled {{if !$observer_activity.$verb}} link-secondary{{/if}} wall-item-{{$response.button.class}}"
						id="pinned-item-{{$verb}}-{{$id}}">
						<i class="bi bi-{{$response.button.icon}} generic-icons"></i>{{if $response.count}}<span
							style="display: inline-block; margin-top: -.25rem;" class="align-top">{{$response.count}}</span>{{/if}}
					</a>
					{{/foreach}}
					<div class="">
						<div id="like-rotator-{{$id}}" class="spinner-wrapper">
							<div class="spinner s"></div>
						</div>
					</div>
				</div>
				<div class="wall-item-tools-right hstack gap-5" id="pinned-item-tools-right-{{$id}}">
					{{if $attachments}}
					<div class="">
						<a type="button" class="wall-item-attach link-secondary" data-bs-toggle="dropdown"
							id="pinned-attachment-menu-{{$id}}"><i class="bi bi-paperclip generic-icons"></i></a>
						<div class="dropdown-menu dropdown-menu-end">{{$attachments}}</div>
					</div>
					{{/if}}
					<div class="">
						<a type="button" class="link-secondary" data-bs-toggle="dropdown"
							id="wall-item-menu-{{$item.id}}">
							<i class="bi bi-three-dots generic-icons"></i>
						</a>
						<div class="dropdown-menu dropdown-menu-end" role="menu" aria-labelledby="wall-item-menu-{{$item.id}}">
							{{if $plink}}
							<a class="dropdown-item" href="{{$plink.href}}" title="{{$plink.title}}" class="u-url"><i
									class="generic-icons-nav bi bi-box-arrow-up-right"></i>{{$plink.title}}</a>
							{{/if}}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</article><hr>
{{if $hide}}
<script>
	function dopinhide(id) {
		id = id.toString();
		if ($('#pinned-wrapper-' + id).length) {
			$('#pinned-wrapper-' + id).fadeTo('fast', 0.33, function () {this.remove();});
			$.post('pin/hide', {'id': id});
		}
	}
</script>
{{/if}}
<script>
	updateRelativeTime('.autotime');
</script>
