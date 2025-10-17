{{if !$item.threaded && $item.comment_firstcollapsed}}
<div id="hide-comments-outer-{{$item.parent}}" class="d-flex justify-content-center hide-comments-outer fakelink small" onclick="showHideComments({{$item.id}});">
  <span class="badge text-bg-info">
    <i id="hide-comments-icon-{{$item.id}}" class="bi bi-chevron-down align-middle hide-comments-icon"></i>
    <span id="hide-comments-label-{{$item.id}}" class="hide-comments-label align-middle"
      data-expanded="{{$item.collapse_comments}}" data-collapsed="{{$item.expand_comments}}">{{$item.expand_comments}}
    </span>
    {{if !$item.threaded}}
    <span id="hide-comments-total-{{$item.id}}" class="hide-comments-label align-middle">{{$item.num_comments}}
    </span>
    {{/if}}
  </span>
</div>
<div id="collapsed-comments-{{$item.id}}" class="collapsed-comments" style="display: none;">
{{/if}}
  {{if $item.toplevel}}
  <div id="thread-wrapper-{{$item.id}}" class="thread-wrapper{{if $item.toplevel}} {{$item.toplevel}} generic-content-wrapper h-entry mb-4{{else}} u-comment h-cite text-body-secondary ps-1 pe-0{{/if}} clearfix{{if $item.is_contained}} is-contained{{/if}}{{if $item.is_new && !$item.event && !$item.photo && !$item.title && !$item.is_comment}} is-new{{/if}}" data-b64mids='{{$item.mids}}'>
    <a name="item_{{$item.id}}"></a>
    <div class="wall-item-outside-wrapper{{if $item.is_comment}} comment{{/if}}{{if $item.previewing}} preview{{/if}}" id="wall-item-outside-wrapper-{{$item.id}}">
      <div class="rounded wall-item-content-wrapper{{if $item.is_comment}} comment{{/if}}" id="wall-item-content-wrapper-{{$item.id}}">
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
              <a href="{{$item.plink.href}}" class="text-decoration-none" title="{{$item.title}} ({{$item.plink.title}})" rel="nofollow"> 
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
        <div class="p-3 pt-2 pb-2 wall-item-content clearfix" id="wall-item-content-{{$item.id}}">
          <div class="wall-item-body e-content" id="wall-item-body-{{$item.id}}" {{if $item.rtl}} dir="rtl" {{/if}}>
            {{$item.body}}
          </div>
        </div>
        {{/if}}
        <div class="ps-2 pt-2 pe-2 wall-item-head{{if !$item.title && !$item.event && !$item.photo}} rounded-top{{/if}} clearfix">
          <div class="entry__meta">
            <div class="entry__meta-author">
                {{if $item.author_is_group_actor}}
                  <svg width="24" height="24" viewBox="-3 -4 26 26" fill="none"  class="bi bi-chat-quote">
                    <path d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894zm-.493 3.905a21.682 21.682 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a9.68 9.68 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9.06 9.06 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105z"/>
                    <path d="M7.066 6.76A1.665 1.665 0 0 0 4 7.668a1.667 1.667 0 0 0 2.561 1.406c-.131.389-.375.804-.777 1.22a.417.417 0 0 0 .6.58c1.486-1.54 1.293-3.214.682-4.112zm4 0A1.665 1.665 0 0 0 8 7.668a1.667 1.667 0 0 0 2.561 1.406c-.131.389-.375.804-.777 1.22a.417.417 0 0 0 .6.58c1.486-1.54 1.293-3.214.682-4.112z"/>
                  </svg>
                {{/if}}

                <a href="{{$item.profile_url}}" title="{{$item.author_id}}" data-bs-toggle="dropdown">{{$item.name}}</a> 
                  {{if $item.thread_author_menu}}
                  <i class="bi bi-caret-down-fill wall-item-photo-caret cursor-pointer" data-bs-toggle="dropdown"></i>
                  <div class="dropdown-menu">
                    {{foreach $item.thread_author_menu as $mitem}}
                    <a class="dropdown-item{{if $mitem.class}} {{$mitem.class}}{{/if}}" {{if $mitem.href}}href="{{$mitem.href}}" {{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}" {{/if}}{{if $mitem.title}}title="{{$mitem.title}}" {{/if}}{{if $mitem.data}}{{$mitem.data}}{{/if}}>{{$mitem.title}}</a>
                    {{/foreach}}
                  </div>
                  {{/if}}
            </div>
            <div class="entry__meta-date">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="7.25" stroke="currentColor" stroke-width="1.5"></circle>
                    <path stroke="currentColor" stroke-width="1.5" d="M12 8V12L14 14"></path>
                </svg>
                  <span class="autotime" title="{{$item.isotime}}"><time class="dt-published" datetime="{{$item.isotime}}">{{$item.localtime}}</time>{{if $item.expiretime}}&nbsp;{{$item.expiretime}}{{/if}}</span>
            </div>
            {{if $item.has_tags}}
              {{$item.mentions}} {{$item.tags}} {{$item.categories}} {{$item.folders}}
            {{/if}}
          </div>
        </div>

        <div class="p-2 pt-1 pb-1 wall-item-tools d-flex justify-content-between">
          <div class="wall-item-tools-left hstack gap-1" id="wall-item-tools-left-{{$item.id}}">
            {{foreach $item.responses as $verb=>$response}}
            {{if !($verb == 'comment' && (($item.toplevel && !$item.blog_mode) || $response.count == 0))}}
            {{if !$item.threaded && $item.blog_mode && $verb == 'comment'}}
            <a href="{{$item.viewthread}}" target="_thread" title="{{$response.count}} {{$response.button.label}}" class="p-4{{if !$item.observer_activity.$verb}} link-secondary{{/if}} wall-item-{{$response.button.class}}" id="wall-item-{{$verb}}-{{$item.id}}">
              <i class="bi bi-chat generic-icons"></i>{{if $response.count}}<span style="display: inline-block; margin-top: -.25rem;" class="align-top">{{$response.count}}</span>{{/if}}
            </a>
            {{else}}
            <a type="button" title="{{$response.count}} {{$response.button.label}}" class="p-4{{if !$item.observer_activity.$verb}} link-secondary{{/if}} wall-item-reaction wall-item-{{$response.button.class}}" id="wall-item-{{$verb}}-{{$item.id}}" data-item-id="{{$item.id}}" data-item-mid="{{$item.rawmid}}" data-item-verb="{{$verb}}" data-item-parent="{{$item.parent}}" data-item-uuid="{{$item.mid}}" data-item-reaction-count="{{$response.count}}">
              <i class="bi bi-{{$response.button.icon}} generic-icons"></i>{{if $response.count}}<span style="display: inline-block; margin-top: -.25rem;" class="align-top">{{$response.count}}</span>{{/if}}
            </a>
            {{/if}}
            {{/if}}
            {{/foreach}}
            {{if $item.toplevel && $item.emojis && $item.reactions}}
            <div class="">
              <a type="button" class="p-4 link-secondary" data-bs-toggle="dropdown" id="wall-item-react-{{$item.id}}">
                <i class="bi bi-emoji-smile generic-icons"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-start container text-center w-25">
                <div class="row g-0">
                  {{foreach $item.reactions as $react}}
                  <div class="col-3 p-2">
                    <a class="" href="#" onclick="jotReact({{$item.id}},'{{$react}}'); return false;"><img class="img-size-32" src="/images/emoji/emojitwo/{{$react}}.png" alt="{{$react}}" /></a>
                  </div>
                  {{/foreach}}
                </div>
              </div>
            </div>
            {{/if}}
            {{if $item.no_comment}}
            <a type="button" class="p-4 link-secondary " href="/search?search={{$item.plink.href}}">
              <i class="bi bi-file-earmark-arrow-down "></i>
            </a>
            {{/if}}
            <div class="">
              <div id="like-rotator-{{$item.id}}" class="spinner-wrapper">
                <div class="spinner s"></div>
              </div>
            </div>
          </div>
          <div class="wall-item-tools-right hstack gap-1" id="wall-item-tools-right-{{$item.id}}">
            {{if $item.moderate}}
            <a href="moderate/{{$item.id}}/approve" onclick="moderate_approve({{$item.id}}); return false;" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i> {{$item.moderate_approve}}</a>
            <a href="moderate/{{$item.id}}/drop" onclick="moderate_drop({{$item.id}}); return false;" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> {{$item.moderate_delete}}</a>
            {{else}}
            {{if $item.star && $item.star.isstarred}}
            <div class="" id="star-button-{{$item.id}}">
              <a type="button" class="p-4 link-secondary wall-item-star" onclick="dostar({{$item.id}});"><i class="bi bi-star generic-icons"></i></a>
            </div>
            {{/if}}
            {{if $item.attachments}}
            <div class="comment__meta">
              <a type="button" class="p-4 link-secondary wall-item-attach" data-bs-toggle="dropdown" id="attachment-menu-{{$item.id}}"><i class="bi bi-paperclip generic-icons"></i></a>
              <div class="dropdown-menu dropdown-menu-end">{{$item.attachments}}</div>
            </div>
            {{/if}}
            {{if $item.reply_to}}
            <div class="comment__meta">
              <div class="comment__reply">
                {{if $item.reply_to}}
                  <a type="button" title="{{$item.reply_to.0}}" class="comment-reply-link"
                    onclick="doreply({{$item.parent}}, {{$item.id}}, '{{$item.author_id}}', '{{$item.reply_to.2}}: {{$item.name|escape:javascript}}');">
                    Reply
                  </a>
                {{/if}}
              </div>
            </div>
            {{/if}}
            <div class="">
              <a type="button" class="p-4 link-secondary" data-bs-toggle="dropdown" id="wall-item-menu-{{$item.id}}">
                <i class="bi bi-three-dots-vertical generic-icons"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-end" role="menu" aria-labelledby="wall-item-menu-{{$item.id}}">
                {{if $item.embed}}
                <a class="dropdown-item" href="#" onclick="jotEmbed({{$item.id}},{{$item.item_type}}); return false"><i
                    class="generic-icons-nav bi bi-arrow-90deg-right"
                    title="{{$item.embed.0}}"></i>{{$item.embed.0}}</a>
                {{/if}}
                {{if $item.plink}}
                <a class="dropdown-item" href="{{$item.plink.href}}" title="{{$item.plink.title}}" class="u-url"><i class="generic-icons-nav bi bi-box-arrow-up-right"></i>{{$item.plink.title}}</a>
                {{/if}}
                {{if $item.edpost}}
                <a class="dropdown-item" href="{{$item.edpost.0}}" title="{{$item.edpost.1}}"><i class="generic-icons-nav bi bi-pencil"></i>{{$item.edpost.1}}</a>
                {{/if}}
                {{if $item.tagger}}
                <a class="dropdown-item" href="#" onclick="itemTag({{$item.id}}); return false;"><i id="tagger-{{$item.id}}" class="generic-icons-nav bi bi-tag" title="{{$item.tagger.tagit}}"></i>{{$item.tagger.tagit}}</a>
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
                <a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}" {{/if}} {{if
                  $mitem.action}}onclick="{{$mitem.action}}" {{/if}} {{if $mitem.title}}title="{{$mitem.title}}"
                  {{/if}}><i class="generic-icons-nav bi bi-{{$mitem.icon}}"></i>{{$mitem.title}}</a>
                {{/foreach}}
                {{/if}}
                {{if $item.drop.dropping}}
                <a class="dropdown-item" href="#" onclick="dropItem('item/drop/{{$item.id}}', '#thread-wrapper-{{$item.id}}', '{{$item.mid}}'); return false;" title="{{$item.drop.delete}}"><i class="generic-icons-nav bi bi-trash"></i>{{$item.drop.delete}}</a>
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
    <div id="load-more-progress-wrapper-{{$item.id}}" class="progress{{if $item.blog_mode}} d-none{{/if}}"  role="progressbar" aria-valuenow="{{$item.comments_total_percent}}" aria-valuemin="0" aria-valuemax="100" style="height: 1px">
      <div id="load-more-progress-{{$item.id}}" class="progress-bar bg-info" style="width: {{$item.comments_total_percent}}%; margin-left: auto; margin-right: auto;" data-comments-total="{{$item.comments_total}}">
      </div>
    </div>
    <div id="load-more-{{$item.id}}" class="load-more text-center text-secondary cursor-pointer{{if $item.blog_mode}} d-none{{/if}}" title="{{$item.load_more_title}}" onclick="request(0, '{{$item.rawmid}}', 'load', {{$item.parent}}, ''); return false;">
      <span id="load-more-dots-{{$item.id}}" class="load-more-dots rounded">
        <span class="dot-1">-</span>
        <span class="dot-2">-</span>
        <span class="dot-3">-</span>
      </span>
    </div>
    {{/if}}
    <div id="comments">
    <ol class="commentlist mt-3" id="wall-item-sub-thread-wrapper-{{$item.id}}">
      {{foreach $item.children as $child}}
      {{include file="{{$child.template}}" item=$child}}
      {{/foreach}}
    </ol>
    </div>
    {{if $item.comment}}
    <div id="wall-item-comment-wrapper-{{$item.id}}" class="p-2 rounded wall-item-comment-wrapper{{if $item.children}} wall-item-comment-wrapper-wc{{/if}}{{if $item.comment_hidden}} d-none{{/if}}">
      {{$item.comment}}
    </div>
    {{/if}}

    {{else}}
    <div class="wall-item-sub-thread-wrapper">
    <ol class="commentlist" id="wall-item-sub-thread-wrapper-{{$item.id}}">
      {{foreach $item.children as $child}}
      {{include file="{{$child.template}}" item=$child}}
      {{/foreach}}
    </ol>
    </div>
    {{/if}}
  </div>
  {{else}}
  <li id="thread-wrapper-{{$item.id}}" class="comment" data-b64mids='{{$item.mids}}'>
    <a name="item_{{$item.id}}"></a>
      <div class="comment__avatar">              
        <img src="{{$item.thumb}}" class="avatar" id="wall-item-photo-{{$item.id}}" alt="{{$item.name}}" loading="lazy" data-bs-toggle="dropdown"/>
        {{if $item.thread_author_menu}}
        <i class="bi bi-caret-down-fill wall-item-photo-caret cursor-pointer" data-bs-toggle="dropdown"></i>
        <div class="dropdown-menu">
          {{foreach $item.thread_author_menu as $mitem}}
          <a class="dropdown-item{{if $mitem.class}} {{$mitem.class}}{{/if}}" {{if $mitem.href}}href="{{$mitem.href}}" {{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}" {{/if}}{{if $mitem.title}}title="{{$mitem.title}}" {{/if}}{{if $mitem.data}}{{$mitem.data}}{{/if}}>{{$mitem.title}}</a>
          {{/foreach}}
        </div>
        {{/if}}

      </div>
      <div class="comment__content">
          <div class="comment__info">
          <div class="comment__author"><a href="{{$item.profile_url}}" title="{{$item.author_id}}">{{$item.name}}</a></div>

              <div class="comment__meta">
                  <div class="comment__time"><span class="autotime" title="{{$item.isotime}}"><time class="dt-published" datetime="{{$item.isotime}}">{{$item.localtime}}</time>{{if $item.expiretime}}&nbsp;{{$item.expiretime}}{{/if}}</span></div>
                  <div class="comment__reply">
                    {{if $item.reply_to}}
                      <a type="button" title="{{$item.reply_to.0}}" class="comment-reply-link"
                        onclick="doreply({{$item.parent}}, {{$item.id}}, '{{$item.author_id}}', '{{$item.reply_to.2}}: {{$item.name|escape:javascript}}');">
                        Reply
                      </a>
                    {{/if}}
                  </div>
              </div>
          </div>

          <div class="comment__text">
          {{$item.body}}
          </div>
      </div>

      <div class="rounded wall-item-content-wrapper{{if $item.is_comment}} comment{{/if}}" id="wall-item-content-wrapper-{{$item.id}}">
        {{if $item.divider}}
        <hr class="wall-item-divider">
        {{/if}}
        {{if $item.has_tags}}
        <div class="p-2 wall-item-tools clearfix">
          <div class="body-tags">
            <span class="tag">{{$item.mentions}} {{$item.tags}} {{$item.categories}} {{$item.folders}}</span>
          </div>
        </div>
        {{/if}}
        <div class="p-2 pt-1 pb-1 wall-item-tools d-flex justify-content-between">
          <div class="wall-item-tools-left hstack gap-1" id="wall-item-tools-left-{{$item.id}}">
            {{foreach $item.responses as $verb=>$response}}
            {{if !($verb == 'comment' && (($item.toplevel && !$item.blog_mode) || $response.count == 0))}}
            {{if !$item.threaded && $item.blog_mode && $verb == 'comment'}}
            <a href="{{$item.viewthread}}" target="_thread" title="{{$response.count}} {{$response.button.label}}" class="p-2{{if !$item.observer_activity.$verb}} link-secondary{{/if}} wall-item-{{$response.button.class}}" id="wall-item-{{$verb}}-{{$item.id}}">
              <i class="bi bi-chat generic-icons"></i>{{if $response.count}}<span style="display: inline-block; margin-top: -.25rem;" class="align-top">{{$response.count}}</span>{{/if}}
            </a>
            {{else}}
            <a type="button" title="{{$response.count}} {{$response.button.label}}" class="p-2{{if !$item.observer_activity.$verb}} link-secondary{{/if}} wall-item-reaction wall-item-{{$response.button.class}}" id="wall-item-{{$verb}}-{{$item.id}}" data-item-id="{{$item.id}}" data-item-mid="{{$item.rawmid}}" data-item-verb="{{$verb}}" data-item-parent="{{$item.parent}}" data-item-uuid="{{$item.mid}}" data-item-reaction-count="{{$response.count}}">
              <i class="bi bi-{{$response.button.icon}} generic-icons"></i>{{if $response.count}}<span style="display: inline-block; margin-top: -.25rem;" class="align-top">{{$response.count}}</span>{{/if}}
            </a>
            {{/if}}
            {{/if}}
            {{/foreach}}
            {{if $item.toplevel && $item.emojis && $item.reactions}}
            <div class="">
              <a type="button" class="p-4 link-secondary" data-bs-toggle="dropdown" id="wall-item-react-{{$item.id}}">
                <i class="bi bi-emoji-smile generic-icons"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-start container text-center w-25">
                <div class="row g-0">
                  {{foreach $item.reactions as $react}}
                  <div class="col-3 p-2">
                    <a class="" href="#" onclick="jotReact({{$item.id}},'{{$react}}'); return false;"><img class="img-size-32" src="/images/emoji/emojitwo/{{$react}}.png" alt="{{$react}}" /></a>
                  </div>
                  {{/foreach}}
                </div>
              </div>
            </div>
            {{/if}}
            {{if $item.no_comment}}
            <a type="button" class="p-4 link-secondary " href="/search?search={{$item.plink.href}}">
              <i class="bi bi-file-earmark-arrow-down "></i>
            </a>
            {{/if}}
            <div class="">
              <div id="like-rotator-{{$item.id}}" class="spinner-wrapper">
                <div class="spinner s"></div>
              </div>
            </div>
          </div>
          <div class="wall-item-tools-right hstack gap-1" id="wall-item-tools-right-{{$item.id}}">
            {{if $item.moderate}}
            <a href="moderate/{{$item.id}}/approve" onclick="moderate_approve({{$item.id}}); return false;" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i> {{$item.moderate_approve}}</a>
            <a href="moderate/{{$item.id}}/drop" onclick="moderate_drop({{$item.id}}); return false;" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> {{$item.moderate_delete}}</a>
            {{else}}
            {{if $item.star && $item.star.isstarred}}
            <div class="" id="star-button-{{$item.id}}">
              <a type="button" class="p-4 link-secondary wall-item-star" onclick="dostar({{$item.id}});"><i class="bi bi-star generic-icons"></i></a>
            </div>
            {{/if}}
            {{if $item.attachments}}
            <div class="">
              <a type="button" class="p-4 link-secondary wall-item-attach" data-bs-toggle="dropdown" id="attachment-menu-{{$item.id}}"><i class="bi bi-paperclip generic-icons"></i></a>
              <div class="dropdown-menu dropdown-menu-end">{{$item.attachments}}</div>
            </div>
            {{/if}}
            {{if $item.reply_to}}
            <a type="button" title="{{$item.reply_to.0}}" class="p-4 link-secondary"
              onclick="doreply({{$item.parent}}, {{$item.id}}, '{{$item.author_id}}', '{{$item.reply_to.2}}: {{$item.name|escape:javascript}}');">
              <i class="bi bi-arrow-90deg-left generic-icons"></i>
            </a>
            {{/if}}
            <div class="">
              <a type="button" class="p-4 link-secondary" data-bs-toggle="dropdown" id="wall-item-menu-{{$item.id}}">
                <i class="bi bi-three-dots-vertical generic-icons"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-end" role="menu" aria-labelledby="wall-item-menu-{{$item.id}}">
                {{if $item.embed}}
                <a class="dropdown-item" href="#" onclick="jotEmbed({{$item.id}},{{$item.item_type}}); return false"><i
                    class="generic-icons-nav bi bi-arrow-90deg-right"
                    title="{{$item.embed.0}}"></i>{{$item.embed.0}}</a>
                {{/if}}
                {{if $item.plink}}
                <a class="dropdown-item" href="{{$item.plink.href}}" title="{{$item.plink.title}}" class="u-url"><i class="generic-icons-nav bi bi-box-arrow-up-right"></i>{{$item.plink.title}}</a>
                {{/if}}
                {{if $item.edpost}}
                <a class="dropdown-item" href="{{$item.edpost.0}}" title="{{$item.edpost.1}}"><i class="generic-icons-nav bi bi-pencil"></i>{{$item.edpost.1}}</a>
                {{/if}}
                {{if $item.tagger}}
                <a class="dropdown-item" href="#" onclick="itemTag({{$item.id}}); return false;"><i id="tagger-{{$item.id}}" class="generic-icons-nav bi bi-tag" title="{{$item.tagger.tagit}}"></i>{{$item.tagger.tagit}}</a>
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
                <a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}" {{/if}} {{if
                  $mitem.action}}onclick="{{$mitem.action}}" {{/if}} {{if $mitem.title}}title="{{$mitem.title}}"
                  {{/if}}><i class="generic-icons-nav bi bi-{{$mitem.icon}}"></i>{{$mitem.title}}</a>
                {{/foreach}}
                {{/if}}
                {{if $item.drop.dropping}}
                <a class="dropdown-item" href="#" onclick="dropItem('item/drop/{{$item.id}}', '#thread-wrapper-{{$item.id}}', '{{$item.mid}}'); return false;" title="{{$item.drop.delete}}"><i class="generic-icons-nav bi bi-trash"></i>{{$item.drop.delete}}</a>
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
    {{if $item.thread_level == 1}}
    <ul class="children" id="wall-item-sub-thread-wrapper-{{$item.id}}">
      {{foreach $item.children as $child}}
      {{include file="{{$child.template}}" item=$child}}
      {{/foreach}}
    </ul>
    {{if $item.comment}}
    <div id="wall-item-comment-wrapper-{{$item.id}}" class="p-2 rounded wall-item-comment-wrapper{{if $item.children}} wall-item-comment-wrapper-wc{{/if}}{{if $item.comment_hidden}} d-none{{/if}}">
      {{$item.comment}}
    </div>
    {{/if}}

    {{else}}
      {{foreach $item.children as $child}}
      {{include file="{{$child.template}}" item=$child}}
      {{/foreach}}
    {{/if}}
  </li>
  {{/if}}
  {{if !$item.threaded && $item.comment_lastcollapsed}}
</div>
  {{/if}}


