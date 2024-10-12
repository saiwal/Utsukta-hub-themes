<div id="thread-wrapper-{{$item.id}}" class="thread-wrapper{{if $item.toplevel}} {{$item.toplevel}} card clearfix generic-content-wrapper mt-2 mb-2{{/if}}" data-b64mids='{{$item.mids}}'>
	<a name="{{$item.id}}" ></a>
	<div class="clearfix wall-item-outside-wrapper {{$item.indent}}{{$item.previewing}}{{if $item.owner_url}} wallwall{{/if}}" id="wall-item-outside-wrapper-{{$item.id}}" >
		<div class="wall-item-content-wrapper {{$item.indent}}" id="wall-item-content-wrapper-{{$item.id}}">
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
			{{if $item.title && !$item.event}}
			<div class="p-2{{if $item.is_new}} bg-primary text-white{{/if}} wall-item-title h3{{if !$item.photo}} rounded-top{{/if}}" id="wall-item-title-{{$item.id}}">
				{{if $item.title_tosource}}{{if $item.plink}}<a href="{{$item.plink.href}}" title="{{$item.title}} ({{$item.plink.title}})">{{/if}}{{/if}}{{$item.title}}{{if $item.title_tosource}}{{if $item.plink}}</a>{{/if}}{{/if}}
			</div>
			{{if ! $item.is_new}}
			<hr class="m-0">
			{{/if}}
			{{/if}}
			<div class="p-2 wall-item-head{{if !$item.title && !$item.event && !$item.photo}} rounded-top{{/if}}{{if $item.is_new && !$item.event && !$item.is_comment}} wall-item-head-new{{/if}}" >
				<div class="lh-sm text-end float-end">
					<div class="wall-item-ago opacity-75" id="wall-item-ago-{{$item.id}}">
						{{if $item.location}}
						{{$item.location}}
						{{/if}}
						{{if $item.delayed}}
						<i class="bi bi-clock"></i>
						{{/if}}
						{{if $item.editedtime}}
						<i class="bi bi-pencil"></i>
						{{/if}}
						{{if $item.verified}}
						<i class="bi bi-shield-check" title="{{$item.verified}}"></i>
						{{elseif $item.forged}}
						<i class="bi bi-shield-exclamation text-danger" title="{{$item.forged}}"></i>
						{{/if}}
						<small class="autotime" title="{{$item.isotime}}"><time class="dt-published" datetime="{{$item.isotime}}">{{$item.localtime}}</time>{{if $item.editedtime}}&nbsp;{{$item.editedtime}}{{/if}}{{if $item.expiretime}}&nbsp;{{$item.expiretime}}{{/if}}</small>
					</div>
					{{if $item.pinned}}
					<div class="wall-item-pinned" title="{{$item.pinned}}" id="wall-item-pinned-{{$item.id}}"><i class="bi bi-pin-fill"></i></div>
					{{/if}}
				</div>
				<div class="float-start wall-item-info pe-2" id="wall-item-info-{{$item.id}}" >
					<div class="wall-item-photo-wrapper{{if $item.owner_url}} wwfrom{{/if}} h-card p-author" id="wall-item-photo-wrapper-{{$item.id}}">
						{{if $item.contact_id}}
						<div class="spinner-wrapper contact-edit-rotator contact-edit-rotator-{{$item.contact_id}}"><div class="spinner s"></div></div>
						{{/if}}
						<img src="{{$item.thumb}}" class="fakelink wall-item-photo{{$item.sparkle}} u-photo p-name" id="wall-item-photo-{{$item.id}}" alt="{{$item.name}}" loading="lazy" data-bs-toggle="dropdown" />
						{{if $item.author_is_group_actor}}
						<i class="bi bi-chat-quote-fill wall-item-photo-group-actor" title="{{$item.author_is_group_actor}}"></i>
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
				<div class="wall-item-author">
					{{if $item.previewing}}
					<div class="float-start me-1 preview-indicator">
						<i class="bi bi-eye" title="{{$item.preview_lbl}}"></i>
					</div>
					{{/if}}
					{{if $item.lock}}
					<div class="float-start dropdown wall-item-lock">
						<i class="bi {{if $item.locktype == 2}}bi-envelope{{else if $item.locktype == 1}}bi-lock{{else}}bi-unlock{{/if}} lockview{{if $item.privacy_warning}} text-danger{{/if}}" data-bs-toggle="dropdown" title="{{$item.lock}}" onclick="lockview('item',{{$item.id}});" ></i>&nbsp;
						<div id="panel-{{$item.id}}" class="dropdown-menu"></div>
					</div>
					{{/if}}
					<div class="text-truncate">
						<a href="{{$item.profile_url}}" class="lh-sm wall-item-name-link u-url"{{if $item.app}} title="{{$item.str_app}}"{{/if}}><span class="wall-item-name{{$item.sparkle}}" id="wall-item-name-{{$item.id}}" ><bdi>{{$item.name}}</bdi></span></a>{{if $item.owner_url}}&nbsp;{{$item.via}}&nbsp;<a href="{{$item.owner_url}}" title="{{$item.olinktitle}}" class="wall-item-name-link"><span class="wall-item-name{{$item.osparkle}}" id="wall-item-ownername-{{$item.id}}"><bdi>{{$item.owner_name}}</bdi></span></a>{{/if}}
					</div>
					<small class="lh-sm text-truncate d-block wall-item-addr opacity-75">{{$item.author_id}}</small>
				</div>
			</div>
			{{if $item.divider}}
			<hr class="wall-item-divider">
			{{/if}}
			{{if $item.body}}
			<div class="p-2 clrearfix {{if $item.is_photo}} wall-photo-item{{else}} wall-item-content{{/if}}" id="wall-item-content-{{$item.id}}">
				<div class="wall-item-body" id="wall-item-body-{{$item.id}}"{{if $item.rtl}} dir="rtl"{{/if}}>
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
			<div class="p-2 clearfix wall-item-tools">
				<div class="float-end wall-item-tools-right hstack gap-1" id="wall-item-tools-right-{{$item.id}}">
					{{if $item.moderate}}
					<a href="moderate/{{$item.id}}/approve" onclick="moderate_approve({{$item.id}}); return false;" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg" ></i> {{$item.moderate_approve}}</a>
					<a href="moderate/{{$item.id}}/drop" onclick="moderate_drop({{$item.id}}); return false;" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash" ></i> {{$item.moderate_delete}}</a>
					{{else}}
					{{if $item.star && $item.star.isstarred}}
					<div class="" id="star-button-{{$item.id}}">
						<button type="button" class="btn btn-sm btn-link link-secondary wall-item-star" onclick="dostar({{$item.id}});"><i class="bi bi-star generic-icons"></i></button>
					</div>
					{{/if}}
					{{if $item.attachments}}
					<div class="">
						<button type="button" class="btn btn-sm btn-link link-secondary wall-item-attach" data-bs-toggle="dropdown" id="attachment-menu-{{$item.id}}"><i class="bi bi-paperclip generic-icons"></i></button>
						<div class="dropdown-menu dropdown-menu-end">{{$item.attachments}}</div>
					</div>
					{{/if}}
					<div class="">
						<button type="button" class="btn btn-sm btn-link link-secondary" data-bs-toggle="dropdown" id="wall-item-menu-{{$item.id}}">
							<i class="bi bi-three-dots-vertical generic-icons"></i>
						</button>
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
							<a class="dropdown-item conversation-settings-link" href="" data-bs-toggle="modal" data-bs-target="#conversation_settings">{{$item.settings}}</a>
							{{/if}}
						</div>
					</div>
					{{/if}}
				</div>
			</div>
		</div>
		{{if $item.conv}}
		<div class="p-2 wall-item-conv" id="wall-item-conv-{{$item.id}}" >
			<a href='{{$item.conv.href}}' id='context-{{$item.id}}' title='{{$item.conv.title}}'>{{$item.conv.title}}</a>
		</div>
		{{/if}}
	</div>
</div>

