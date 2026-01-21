{{if !$item.threaded && $item.comment_firstcollapsed}}
<div id="hide-comments-outer-{{$item.parent}}" class="hide-comments-outer fakelink small" onclick="showHideComments({{$item.id}});">
	<i id="hide-comments-icon-{{$item.id}}" class="bi bi-chevron-down align-middle hide-comments-icon"></i> <span id="hide-comments-label-{{$item.id}}" class="hide-comments-label align-middle" data-expanded="{{$item.collapse_comments}}" data-collapsed="{{$item.expand_comments}}">{{$item.expand_comments}}</span>{{if !$item.threaded}}&nbsp;<span id="hide-comments-total-{{$item.id}}" class="hide-comments-label align-middle">{{$item.num_comments}}</span>{{/if}}
</div>
<div id="collapsed-comments-{{$item.id}}" class="collapsed-comments" style="display: none;">
{{/if}}
	<div id="thread-wrapper-{{$item.id}}" class="thread-wrapper{{if $item.toplevel}} entry {{$item.toplevel}} generic-content-wrapper h-entry{{else}} u-comment h-cite{{/if}} clearfix{{if $item.is_contained}} is-contained{{/if}}{{if $item.is_new && !$item.event && !$item.photo && !$item.title && !$item.is_comment}} is-new{{/if}}" data-b64mids='{{$item.mids}}'>
		<a name="item_{{$item.id}}" ></a>
		<div class="wall-item-outside-wrapper{{if $item.is_comment}} comment{{/if}}{{if $item.previewing}} preview{{/if}}" id="wall-item-outside-wrapper-{{$item.id}}" >
			<div class="rounded wall-item-content-wrapper{{if $item.is_comment}} comment{{/if}}" id="wall-item-content-wrapper-{{$item.id}}">
				{{if $item.photo}}
				<div class="wall-photo-item" id="wall-photo-item-{{$item.id}}">
					{{$item.photo}}
				</div>
				{{/if}}
				{{if $item.event}}
				<div class="wall-event-item" id="wall-event-item-{{$item.id}}">
					{{$item.event}}
				</div>
				{{/if}}
				{{if $item.title && $item.toplevel && !$item.event}}
				<h2 class="entry__title h1">
					{{if $item.title_tosource}}{{if $item.plink}}
					<a href="{{$item.plink.href}}" title="{{$item.title}} {{$item.plink.title}}">{{$item.title}}
					{{/if}}{{/if}}
						{{$item.title}}
					{{if $item.title_tosource}}{{if $item.plink}}
					</a>
					{{/if}}{{/if}}
        </h2>
				{{/if}}
				<div class="entry__meta">
						<ul class="mb-2">
							<li><img src="{{$item.thumb}}" alt=""></li>
							<li>
								{{if $item.lock}}
								<span class="float-start dropdown wall-item-lock">
									<i class="bi {{if $item.locktype == 2}}bi-envelope{{else if $item.locktype == 1}}bi-lock{{else}}bi-unlock{{/if}} lockview{{if $item.privacy_warning}} text-danger{{/if}}" data-bs-toggle="dropdown" title="{{$item.lock}}" onclick="lockview('item',{{$item.id}});" ></i>&nbsp;
									<div id="panel-{{$item.id}}" class="dropdown-menu"></div>
								</span>
								{{/if}}
								<span class="text-truncate">
									<a href="{{$item.profile_url}}" class="lh-sm wall-item-name-link u-url"{{if $item.app}} title="{{$item.str_app}}"{{/if}}><span class="wall-item-name{{$item.sparkle}}" id="wall-item-name-{{$item.id}}" ><bdi>{{$item.name}}</bdi></span></a>{{if $item.owner_url}}&nbsp;{{$item.via}}&nbsp;<a href="{{$item.owner_url}}" title="{{$item.owner_addr}}" class="wall-item-name-link"><span class="wall-item-name{{$item.osparkle}}" id="wall-item-ownername-{{$item.id}}"><bdi>{{$item.owner_name}}</bdi></span></a>{{/if}}
								</span>
							</li>
							{{if $item.location}}
							{{$item.location}}
							{{/if}}
							{{if $item.editedtime}}
							<li><i class="bi bi-pencil" title="{{$item.editedtime}}"></i></li>
							{{/if}}
							{{if $item.verified}}
							<li><i class="bi bi-shield-check" title="{{$item.verified}}"></i></li>
							{{elseif $item.forged}}
							<li><i class="bi bi-shield-exclamation text-danger" title="{{$item.forged}}"></i></li>
							{{/if}}
							{{if $item.no_comment}}
							<li><i class="bi bi-ban" title="{{$item.no_comment}}"></i></li>
							{{/if}}
							{{if $item.delayed}}
							<li><i class="bi bi-clock" title="{{$item.delayed}}"></i></li>
							{{/if}}
							{{if $item.expiretime}}
							<li><i class="bi bi-clock-history" title="{{$item.expiretime}}"></i></li>
							{{/if}}
							<li><span class="autotime" title="{{$item.isotime}}"><time class="dt-published" datetime="{{$item.isotime}}">{{$item.localtime}}</time>{{if $item.expiretime}}&nbsp;{{$item.expiretime}}{{/if}}</span></li>
						</ul>
				</div>
				{{if $item.divider}}
				<hr class="wall-item-divider">
				{{/if}}
				{{if $item.body}}
				<div class="p-2 wall-item-content clearfix" id="wall-item-content-{{$item.id}}">
					<div class="wall-item-body e-content" id="wall-item-body-{{$item.id}}"{{if $item.rtl}} dir="rtl"{{/if}}>
						{{$item.body}}
					</div>
				</div>
				{{/if}}
				{{if $item.has_tags}}
				<div class="p-2 wall-item-tools clearfix">
					<div class="body-tags">
						<span class="tag">{{$item.mentions}} {{$item.tags}} {{$item.categories}} {{$item.folders}}</span>
					</div>
				</div>
				{{/if}}

				<div class="p-2 wall-item-tools d-flex justify-content-between">
					<div class="wall-item-tools-left hstack gap-4" id="wall-item-tools-left-{{$item.id}}">
						{{foreach $item.responses as $verb=>$response}}
						{{if !($verb == 'comment' && (($item.toplevel && !$item.blog_mode) || $response.count == 0))}}
						{{if !$item.threaded && $item.blog_mode && $verb == 'comment'}}
						<a href="{{$item.viewthread}}" target="_thread" title="{{$response.count}} {{$response.button.label}}" class="{{if !$item.observer_activity.$verb}} link-secondary{{/if}} wall-item-{{$response.button.class}}" id="wall-item-{{$verb}}-{{$item.id}}">
							<i class="bi bi-chat generic-icons"></i>{{if $response.count}}<span style="display: inline-block; margin-top: -.25rem;" class="align-top">{{$response.count}}</span>{{/if}}
						</a>
						{{else}}
						<a type="button" title="{{$response.count}} {{$response.button.label}}" class="{{if !$item.observer_activity.$verb}} link-secondary{{/if}} wall-item-reaction wall-item-{{$response.button.class}}" id="wall-item-{{$verb}}-{{$item.id}}" data-item-id="{{$item.id}}" data-item-mid="{{$item.rawmid}}" data-item-verb="{{$verb}}" data-item-parent="{{$item.parent}}" data-item-uuid="{{$item.mid}}" data-item-reaction-count="{{$response.count}}">
							<i class="bi bi-{{$response.button.icon}} generic-icons"></i>{{if $response.count}}<span style="display: inline-block; margin-top: -.25rem;" class="align-top">{{$response.count}}</span>{{/if}}
						</a>
						{{/if}}
						{{/if}}
						{{/foreach}}
						{{if $item.toplevel && $item.emojis && $item.reactions}}
						<div class="">
							<a type="button" class="link-secondary" data-bs-toggle="dropdown" id="wall-item-react-{{$item.id}}">
								<i class="bi bi-emoji-smile generic-icons"></i>
							</a>
							<div class="dropdown-menu dropdown-menu-start container text-center w-25">
								<div class="row g-0">
									{{foreach $item.reactions as $react}}
									<div class="col-3 p-2">
										<a class="" href="#" onclick="jotReact({{$item.id}},'{{$react}}'); return false;"><img class="menu-img-1" src="/images/emoji/emojitwo/{{$react}}.png" alt="{{$react}}" /></a>
									</div>
									{{/foreach}}
								</div>
							</div>
						</div>
						{{/if}}
						<div class="">
							<div id="like-rotator-{{$item.id}}" class="spinner-wrapper">
								<div class="spinner s"></div>
							</div>
						</div>
					</div>
					<div class="wall-item-tools-right hstack gap-4" id="wall-item-tools-right-{{$item.id}}">
						{{if $item.moderate}}
						<a href="moderate/{{$item.id}}/approve" onclick="moderate_approve({{$item.id}}); return false;" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg" ></i> {{$item.moderate_approve}}</a>
						<a href="moderate/{{$item.id}}/drop" onclick="moderate_drop({{$item.id}}); return false;" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash" ></i> {{$item.moderate_delete}}</a>
						{{else}}
						{{if $item.star && $item.star.isstarred}}
						<div class="" id="star-button-{{$item.id}}">
							<a type="button" class="link-secondary wall-item-star" onclick="dostar({{$item.id}});"><i class="bi bi-star generic-icons"></i></a>
						</div>
						{{/if}}
						{{if $item.attachments}}
						<div class="">
							<a type="button" class="p-2 link-secondary wall-item-attach" data-bs-toggle="dropdown" id="attachment-menu-{{$item.id}}"><i class="bi bi-paperclip generic-icons"></i></a>
							<div class="dropdown-menu dropdown-menu-end">{{$item.attachments}}</div>
						</div>
						{{/if}}
						{{if $item.reply_to}}
						<a type="button" title="{{$item.reply_to.0}}" class="link-secondary" onclick="doreply({{$item.parent}}, {{$item.id}}, '{{$item.author_id}}', '{{$item.reply_to.2}}: {{$item.name|escape:javascript}}');">
							<i class="bi bi-arrow-90deg-left generic-icons" ></i>
						</a>
						{{/if}}
						<div class="">
							<a type="button" class="link-secondary" data-bs-toggle="dropdown" id="wall-item-menu-{{$item.id}}">
								<i class="bi bi-three-dots generic-icons"></i>
							</a>
							<div class="dropdown-menu dropdown-menu-end" role="menu" aria-labelledby="wall-item-menu-{{$item.id}}">
								{{if $item.embed}}
								<a class="dropdown-item" href="#" onclick="jotEmbed({{$item.id}},{{$item.item_type}}); return false"><i class="generic-icons-nav bi bi-arrow-90deg-right" title="{{$item.embed.0}}"></i>{{$item.embed.0}}</a>
								{{/if}}
								{{if $item.plink}}
								<a class="dropdown-item" href="{{$item.plink.href}}" title="{{$item.plink.title}}" class="u-url"><i class="generic-icons-nav bi bi-box-arrow-up-right"></i>{{$item.plink.title}}</a>
								{{/if}}
								{{if $item.edpost}}
								<a class="dropdown-item" href="{{$item.edpost.0}}" title="{{$item.edpost.1}}"><i class="generic-icons-nav bi bi-pencil"></i>{{$item.edpost.1}}</a>
								{{/if}}
								{{if $item.tagger}}
								<a class="dropdown-item" href="#"  onclick="itemTag({{$item.id}}); return false;"><i id="tagger-{{$item.id}}" class="generic-icons-nav bi bi-tag" title="{{$item.tagger.tagit}}"></i>{{$item.tagger.tagit}}</a>
								{{/if}}
								{{if $item.filer}}
								<a class="dropdown-item" href="#" onclick="itemFiler({{$item.id}}); return false;"><i id="filer-{{$item.id}}" class="generic-icons-nav bi bi-folder-plus" title="{{$item.filer}}"></i>{{$item.filer}}</a>
								{{/if}}
								{{if $item.pinnable}}
								<a class="dropdown-item dropdown-item-pinnable" href="#" onclick="dopin({{$item.id}}); return false;" id="item-pinnable-{{$item.id}}"><i class="generic-icons-nav bi bi-pin"></i>{{$item.pinme}}</a>
								{{/if}}
								{{if $item.bookmark}}
								<a class="dropdown-item" href="#" onclick="itemBookmark({{$item.id}}); return false;"><i id="bookmarker-{{$item.id}}" class="generic-icons-nav bi bi-bookmark" title="{{$item.bookmark}}"></i>{{$item.bookmark}}</a>
								{{/if}}
								{{if $item.addtocal}}
								<a class="dropdown-item" href="#" onclick="itemAddToCal({{$item.id}}); return false;"><i id="addtocal-{{$item.id}}" class="generic-icons-nav bi bi-calendar-plus" title="{{$item.addtocal}}"></i>{{$item.addtocal}}</a>
								{{/if}}
								{{if $item.star}}
								<a class="dropdown-item" href="#" onclick="dostar({{$item.id}}); return false;"><i id="starred-{{$item.id}}" class="generic-icons-nav bi{{if $item.star.isstarred}} starred bi-star-fill{{else}} unstarred bi-star{{/if}}" title="{{$item.star.toggle}}"></i>{{$item.star.toggle}}</a>
								{{/if}}
								{{if $item.expand}}
								<a class="dropdown-item dropdown-item-expand" href="#" data-item-id="{{$item.id}}" data-item-uuid="{{$item.mid}}"><i id="expand-{{$item.id}}" class="generic-icons-nav bi bi-arrows-angle-expand" title="{{$item.expand}}"></i>{{$item.expand}}</a>
								{{/if}}
								{{if $item.thread_action_menu}}
								{{foreach $item.thread_action_menu as $mitem}}
								<a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}} ><i class="generic-icons-nav bi bi-{{$mitem.icon}}"></i>{{$mitem.title}}</a>
								{{/foreach}}
								{{/if}}
								{{if $item.drop.dropping}}
								<a class="dropdown-item" href="#" onclick="dropItem('item/drop/{{$item.id}}', '#thread-wrapper-{{$item.id}}', '{{$item.mid}}'); return false;" title="{{$item.drop.delete}}" ><i class="generic-icons-nav bi bi-trash"></i>{{$item.drop.delete}}</a>
								{{/if}}
								{{if $item.dropdown_extras}}
								<div class="dropdown-divider"></div>
								{{$item.dropdown_extras}}
								{{/if}}
								{{if $item.edpost && $item.dreport}}
								<div class="dropdown-divider"></div>
								<a class="dropdown-item" href="dreport/{{$item.dreport_link}}">{{$item.dreport}}</a>
								{{/if}}
								{{if $item.settings}}
								<div class="dropdown-divider"></div>
								<a class="dropdown-item conversation-settings-link" href="#" data-bs-toggle="modal" data-bs-target="#conversation_settings">{{$item.settings}}</a>
								{{/if}}
							</div>
						</div>
						{{/if}}
					</div>
				</div>
			</div>
		</div>
		{{if $item.thread_level == 1}}
		{{if $item.toplevel && $item.load_more && $item.threaded}}
		<div id="load-more-progress-wrapper-{{$item.id}}" class="progress{{if $item.blog_mode}} d-none{{/if}}" role="progressbar" aria-valuenow="{{$item.comments_total_percent}}" aria-valuemin="0" aria-valuemax="100" style="height: 1px">
			<div id="load-more-progress-{{$item.id}}" class="progress-bar bg-info" style="width: {{$item.comments_total_percent}}%; margin-left: auto; margin-right: auto;" data-comments-total="{{$item.comments_total}}"></div>
		</div>
		<div id="load-more-{{$item.id}}" class="load-more text-center text-secondary cursor-pointer{{if $item.blog_mode}} d-none{{/if}}" title="{{$item.load_more_title}}" onclick="request(0, '{{$item.rawmid}}', 'load', {{$item.parent}}, ''); return false;">
			<span id="load-more-dots-{{$item.id}}" class="load-more-dots rounded"><span class="dot-1">-</span> <span class="dot-2">-</span> <span class="dot-3">-</span></span>
		</div>
		{{/if}}
		<div id="wall-item-sub-thread-wrapper-{{$item.id}}" class="wall-item-sub-thread-wrapper">
		{{foreach $item.children as $child}}
			{{include file="{{$child.template}}" item=$child}}
		{{/foreach}}
		</div>
		{{if $item.comment}}
		<div id="wall-item-comment-wrapper-{{$item.id}}" class="p-2 rounded wall-item-comment-wrapper{{if $item.children}} wall-item-comment-wrapper-wc{{/if}}{{if $item.comment_hidden}} d-none{{/if}}">
			{{$item.comment}}
		</div>
		{{/if}}

		{{else}}
		<div id="wall-item-sub-thread-wrapper-{{$item.id}}" class="wall-item-sub-thread-wrapper">
		{{foreach $item.children as $child}}
			{{include file="{{$child.template}}" item=$child}}
		{{/foreach}}
		</div>
		{{/if}}
	</div>
{{if !$item.threaded && $item.comment_lastcollapsed}}
</div>
{{/if}}
