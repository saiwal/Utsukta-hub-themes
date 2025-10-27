<article id="thread-wrapper-{{$item.id}}" class="brick entry thread-wrapper {{$item.toplevel}}" data-b64mids='{{$item.mids}}'>
	<a name="{{$item.id}}" ></a>
	<div class="clearfix wall-item-outside-wrapper {{$item.indent}}{{$item.previewing}}{{if $item.owner_url}} wallwall{{/if}}" id="wall-item-outside-wrapper-{{$item.id}}" >
		<div class="wall-item-content-wrapper {{$item.indent}}" id="wall-item-content-wrapper-{{$item.id}}">      
      <div class="entry__header mb-5">
        {{if $item.photo}}
        <div class="wall-photo-item" id="wall-photo-item-{{$item.id}}">
          {{$item.photo}}
        </div>
        {{/if}}
        {{if $item.event}}
        <div class="wall-event-item border-bottom-0" id="wall-event-item-{{$item.id}}">
          {{$item.event}}
        </div>
        {{/if}}
        {{if $item.title && $item.toplevel && !$item.event}}
        <h3 class="wall-item-title  " id="wall-item-title-{{$item.id}}">
          {{if $item.title_tosource}}
          {{if $item.plink}}
          <a href="{{$item.plink.href}}" class="text-decoration-none" title="{{$item.title}} ({{$item.plink.title}})"
            rel="nofollow">
            {{/if}}
            {{/if}}
            {{$item.title}}
            {{if $item.title_tosource}}
            {{if $item.plink}}
          </a>
          {{/if}}
          {{/if}}
        </h3>
        {{/if}}
      </div>
			{{if $item.divider}}
			<hr class="wall-item-divider">
			{{/if}}
			{{if $item.body}}
			<div class="p-2 ps-3 clearfix w-100 {{if $item.is_photo}} wall-photo-item{{else}} wall-item-content{{/if}}" id="wall-item-content-{{$item.id}}">
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
      <div
        class="ps-2 pt-2 pe-2 wall-item-head{{if !$item.title && !$item.event && !$item.photo}} rounded-top{{/if}} clearfix">
        <div class="entry__meta">
          <div class="entry__meta-author">
            {{if $item.author_is_group_actor}}
            <svg width="24" height="24" viewBox="-3 -4 26 26" fill="none" class="bi bi-chat-quote">
              <path
                d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894zm-.493 3.905a21.682 21.682 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a9.68 9.68 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9.06 9.06 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105z" />
              <path
                d="M7.066 6.76A1.665 1.665 0 0 0 4 7.668a1.667 1.667 0 0 0 2.561 1.406c-.131.389-.375.804-.777 1.22a.417.417 0 0 0 .6.58c1.486-1.54 1.293-3.214.682-4.112zm4 0A1.665 1.665 0 0 0 8 7.668a1.667 1.667 0 0 0 2.561 1.406c-.131.389-.375.804-.777 1.22a.417.417 0 0 0 .6.58c1.486-1.54 1.293-3.214.682-4.112z" />
            </svg>
            {{/if}}

            <a href="{{$item.profile_url}}" title="{{$item.author_id}}" data-bs-toggle="dropdown">{{$item.name}}</a>
            {{if $item.thread_author_menu}}
            <i class="bi bi-caret-down-fill wall-item-photo-caret cursor-pointer" data-bs-toggle="dropdown"></i>
            <div class="dropdown-menu">
              {{foreach $item.thread_author_menu as $mitem}}
              <a class="dropdown-item{{if $mitem.class}} {{$mitem.class}}{{/if}}" {{if
                $mitem.href}}href="{{$mitem.href}}" {{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}" {{/if}}{{if
                $mitem.title}}title="{{$mitem.title}}" {{/if}}{{if
                $mitem.data}}{{$mitem.data}}{{/if}}>{{$mitem.title}}</a>
              {{/foreach}}
            </div>
            {{/if}}
          </div>
          <div class="entry__meta-date">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="7.25" stroke="currentColor" stroke-width="1.5"></circle>
              <path stroke="currentColor" stroke-width="1.5" d="M12 8V12L14 14"></path>
            </svg>
            <span class="autotime" title="{{$item.isotime}}"><time class="dt-published"
                datetime="{{$item.isotime}}">{{$item.localtime}}</time>{{if
              $item.expiretime}}&nbsp;{{$item.expiretime}}{{/if}}</span>
          </div>
          {{if $item.has_tags}}
          {{$item.mentions}} {{$item.tags}} {{$item.categories}} {{$item.folders}}
          {{/if}}
        </div>
      </div>

			{{/if}}
			<div class="p-2 clearfix wall-item-tools">
				<div class="float-end wall-item-tools-right hstack gap-1" id="wall-item-tools-right-{{$item.id}}">
					{{if $item.moderate}}
					<a href="moderate/{{$item.id}}/approve" onclick="moderate_approve({{$item.id}}); return false;" class="btn btn-sm btn-success"><i class="bi bi-check-lg" ></i> {{$item.moderate_approve}}</a>
					<a href="moderate/{{$item.id}}/drop" onclick="moderate_drop({{$item.id}}); return false;" class="btn btn-sm btn-danger"><i class="bi bi-trash" ></i> {{$item.moderate_delete}}</a>
					{{else}}
					{{if $item.star && $item.star.isstarred}}
					<div class="" id="star-button-{{$item.id}}">
						<button type="button" class="btn btn-sm btn-link link-secondary wall-item-star" onclick="dostar({{$item.id}});"><i class="bi bi-star generic-icons"></i></button>
					</div>
					{{/if}}
					{{if $item.attachments}}
					<div class="">
						<a type="button" class="wall-item-attach pe-3" data-bs-toggle="dropdown" id="attachment-menu-{{$item.id}}"><i class="bi bi-paperclip generic-icons"></i></a>
						<div class="dropdown-menu dropdown-menu-end">{{$item.attachments}}</div>
					</div>
					{{/if}}
					<div class="">
						<a type="button" class="link-secondary pe-3" data-bs-toggle="dropdown" id="wall-item-menu-{{$item.id}}">
							<i class="bi bi-three-dots-vertical generic-icons"></i>
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
        {{if $item.conv}}
      		<div class="p-2 wall-item-conv card-footer" id="wall-item-conv-{{$item.id}}" >
			      <a href='{{$item.conv.href}}' id='context-{{$item.id}}' title='{{$item.conv.title}}'>{{$item.conv.title}}</a>
      		</div>
    		{{/if}}

			</div>
		</div>
		</div>
</article>

