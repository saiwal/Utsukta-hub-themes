<div id="pinned-wrapper-{{$id}}" class="pinned-item card toplevel_item generic-content-wrapper h-entry" data-b64mids='{{$mids}}'>
	<div class="wall-item-outside-wrapper" id="pinned-item-outside-wrapper-{{$id}}">
		<div class="clearfix wall-item-content-wrapper" id="pinned-item-content-wrapper-{{$id}}">
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
				<div class="{{if $is_new}} bg-primary text-white{{/if}} card-header wall-item-title h3{{if !$photo}} rounded-top{{/if}}" id="pinned-item-title-{{$id}}">
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
			<div class="p-2 lh-sm d-flex wall-item-head{{if !$title && !$event && !$photo}} rounded-top{{/if}}{{if $is_new && !$event}} wall-item-head-new{{/if}} card-body" >
				<div class="wall-item-info pe-2" id="wall-item-info-{{$id}}" >
					<div class="wall-item-photo-wrapper{{if $owner_url}} wwfrom{{/if}} h-card p-author" id="wall-item-photo-wrapper-{{$id}}">
						<img src="{{$thumb}}" class="fakelink wall-item-photo{{$sparkle}} u-photo p-name" id="wall-item-photo-{{$id}}" alt="{{$name}}" loading="lazy" data-bs-toggle="dropdown" />
						{{if $thread_author_menu}}
						<i class="bi bi-caret-down wall-item-photo-caret cursor-pointer" data-bs-toggle="dropdown"></i>
						<div class="dropdown-menu">
							{{foreach $thread_author_menu as $mitem}}
							<a class="dropdown-item{{if $mitem.class}} {{$mitem.class}}{{/if}}" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}}{{if $mitem.data}} {{$mitem.data}}{{/if}}>{{$mitem.title}}</a>
							{{/foreach}}
						</div>
						{{/if}}
					</div>
				</div>
				<div class="wall-item-author text-truncate">
					<a href="{{$profile_url}}" title="{{$linktitle}}" class="wall-item-name-link u-url"><span class="wall-item-name" id="pinned-item-name-{{$id}}" >{{$name}}</span></a>{{if $owner_url}}&nbsp;{{$via}}&nbsp;<a href="{{$owner_url}}" title="{{$olinktitle}}" class="wall-item-name-link"><span class="wall-item-name" id="pinned-item-ownername-{{$id}}">{{$owner_name}}</span></a>{{/if}}<br>
					<small class="wall-item-addr opacity-75">{{$author_id}}</small>
				</div>
				<div class="text-end ms-auto">
					<div class="wall-item-ago text-nowrap opacity-75" id="wall-item-ago-{{$id}}">
						{{if $editedtime}}
						<i class="bi bi-pencil"></i>
						{{/if}}
						{{if $delayed}}
						<i class="bi fa-clock-o"></i>
						{{/if}}
						{{if $location}}
						<small class="wall-item-location p-location" id="wall-item-location-{{$id}}">{{$location}}</small>
						{{/if}}
						{{if $verified}}
						<i class="bi bi-check-lg text-success" title="{{$verified}}"></i>
						{{elseif $forged}}
						<i class="bi fa-exclamation text-danger" title="{{$forged}}"></i>
						{{/if}}
						<small class="autotime" title="{{$isotime}}"><time class="dt-published" datetime="{{$isotime}}">{{$localtime}}</time>{{if $editedtime}}&nbsp;{{$editedtime}}{{/if}}{{if $expiretime}}&nbsp;{{$expiretime}}{{/if}}</small>
					</div>
					<div class="wall-item-pinned" title="{{$pinned}}" id="wall-item-pinned-{{$id}}"><i class="bi fa-thumb-tack"></i></div>
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
				<div class="p-2 clearfix wall-item-tools">
					<div class="float-end wall-item-tools-right">
						<div class="btn-group">
							<div id="pinned-rotator-{{$id}}" class="spinner-wrapper">
								<div class="spinner s"></div>
							</div>
						</div>
						<div class="btn-group">
						{{if $isevent}}
							<div class="btn-group">
								<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="pinned-item-attend-menu-{{$id}}" title="{{$attend_title}}">
									<i class="bi fa-calendar-check-o"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end">
									<a class="dropdown-item" href="#" title="{{$attend.0}}" onclick="itemAddToCal({{$id}}); dolike({{$id}},'attendyes'); return false;">
										<i class="item-act-list bi bi-check-lg{{if $my_responses.attend}} ivoted{{/if}}" ></i> {{$attend.0}}
									</a>
									<a class="dropdown-item" href="#" title="{{$attend.1}}" onclick="itemAddToCal({{$id}}), dolike({{$id}},'attendno'); return false;">
										<i class="item-act-list bi bi-x-lg{{if $my_responses.attendno}} ivoted{{/if}}" ></i> {{$attend.1}}
									</a>
									<a class="dropdown-item" href="#" title="{{$attend.2}}" onclick="itemAddToCal({{$id}}); dolike({{$id}},'attendmaybe'); return false;">
										<i class="item-act-list bi bi-question-lg{{if $my_responses.attendmaybe}} ivoted{{/if}}" ></i> {{$attend.2}}
									</a>
								</div>
							</div>
						{{/if}}
						{{if $canvote}}
							<div class="btn-group">
								<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="pinned-item-consensus-menu-{{$id}}" title="{{$vote_title}}">
									<i class="bi bi-check-square"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end" role="menu" aria-labelledby="wall-item-consensus-menu-{{$id}}">
									<a class="dropdown-item" href="#" title="{{$conlabels.0}}" onclick="dolike({{$id}},'agree'); return false;">
										<i class="item-act-list bi bi-check-lg{{if $my_responses.agree}} ivoted{{/if}}" ></i> {{$conlabels.0}}
									</a>
									<a class="dropdown-item" href="#" title="{{$conlabels.1}}" onclick="dolike({{$id}},'disagree'); return false;">
										<i class="item-act-list bi bi-x-lg{{if $my_responses.disagree}} ivoted{{/if}}" ></i> {{$conlabels.1}}
									</a>
									<a class="dropdown-item" href="#" title="{{$conlabels.2}}" onclick="dolike({{$id}},'abstain'); return false;">
										<i class="item-act-list bi bi-question-lg{{if $my_responses.abstain}} ivoted{{/if}}" ></i> {{$conlabels.2}}
									</a>
								</div>
							</div>
						{{/if}}
						<div class="btn-group">
							<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="pinned-item-menu-{{$id}}">
								<i class="bi bi-gear"></i>
							</button>
							<div class="dropdown-menu dropdown-menu-end" role="menu" aria-labelledby="wall-item-menu-{{$id}}">
								{{if $share}}
									<a class="dropdown-item" href="#" onclick="jotShare({{$id}},{{$item_type}}); return false;"><i class="generic-icons-nav bi fa-retweet" title="{{$share.0}}"></i>{{$share.0}}</a>
								{{/if}}
								{{if $embed}}
									<a class="dropdown-item" href="#" onclick="jotEmbed({{$id}},{{$item_type}}); return false;"><i class="generic-icons-nav bi fa-share" title="{{$embed.0}}"></i>{{$embed.0}}</a>
								{{/if}}
								{{if $plink}}
									<a class="dropdown-item" href="{{$plink.href}}" title="{{$plink.title}}" class="u-url"><i class="generic-icons-nav bi bi-box-arrow-up-right"></i>{{$plink.title}}</a>
								{{/if}}
								{{if $pinme}}
								    <a class="dropdown-item dropdown-item-pinnable" href="#" onclick="dopin({{$id}}); return false;"><i class="generic-icons-nav bi fa-thumb-tack"></i>{{$pinme}}</a>
								{{/if}}
								{{if $hide}}
									<a class="dropdown-item" href="#" onclick="dopinhide({{$id}}); return false;" class="u-url"><i class="generic-icons-nav bi fa-remove"></i>{{$hide}}</a>
								{{/if}}
							</div>
						</div>
					</div>
				</div>
				{{if $responses || $attachments}}
					<div class="wall-item-tools-left btn-group" id="pinned-item-tools-left-{{$id}}">
						{{if $attachments}}
							<div class="wall-item-tools-left btn-group" id="pinned-item-tools-left-{{$id}}">
								<div class="btn-group">
									<button type="button" class="btn btn-outline-secondary btn-sm wall-item-like dropdown-toggle" data-bs-toggle="dropdown" id="pinned-attachment-menu-{{$id}}">
										<i class="bi bi-paperclip"></i>
									</button>
									<div class="dropdown-menu">{{$attachments}}</div>
								</div>
							</div>
						{{/if}}
						{{foreach $responses as $verb=>$response}}
							{{if $response.count}}
								<div class="btn-group">
									<button type="button" class="btn btn-outline-secondary btn-sm wall-item-like dropdown-toggle"{{if $response.modal}} data-bs-toggle="modal" data-bs-target="#{{$verb}}Modal-{{$id}}"{{else}} data-bs-toggle="dropdown"{{/if}} id="pinned-item-{{$verb}}-{{$id}}">{{$response.count}} {{$response.button}}</button>
									{{if $response.modal}}
										<div class="modal" id="pinned-{{$verb}}Modal-{{$id}}">
											<div class="modal-dialog">
												<div class="modal-content">
													<div class="modal-header">
														<h3 class="modal-title">{{$response.count}} {{$response.button}}</h3>
														<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
													</div>
													<div class="modal-body response-list">
														<ul class="nav nav-pills flex-column">
															{{foreach $response.list as $liker}}<li class="nav-item">{{$liker}}</li>{{/foreach}}
														</ul>
													</div>
													<div class="modal-footer clear">
														<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$modal_dismiss}}</button>
													</div>
												</div>
											</div>
										</div>
									{{else}}
										<div class="dropdown-menu">
											{{foreach $response.list as $liker}}{{$liker}}{{/foreach}}
										</div>
									{{/if}}
								</div>
							{{/if}}
						{{/foreach}}
					</div>
				{{/if}}
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
