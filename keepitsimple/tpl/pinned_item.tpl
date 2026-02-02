<div id="pinned-wrapper-{{$id}}" class="pinned-item toplevel_item generic-content-wrapper h-entry mb-3" data-b64mids='{{$mids}}'>
	<div class="wall-item-outside-wrapper" id="pinned-item-outside-wrapper-{{$id}}">
		<div class="wall-item-content-wrapper" id="pinned-item-content-wrapper-{{$id}}">
      <div class="entry__header mb-5">
        {{if $item.photo}}
        <div class="wall-photo-item" id="wall-photo-item-{{$id}}">
          {{$photo}}
        </div>
        {{/if}}
        {{if $event}}
        <div class="wall-event-item border-bottom-0" id="wall-event-item-{{$item.id}}">
          {{$event}}
        </div>
        {{/if}}
        {{if $title && $toplevel && !$event}}
        <h3 class="wall-item-title  " id="wall-item-title-{{$id}}">
          {{if $title_tosource}}
          {{if $plink}}
          <a href="{{$plink.href}}" class="text-decoration-none" title="{{$title}} ({{$plink.title}})"
            rel="nofollow">
            {{/if}}
            {{/if}}
            {{$title}}
            {{if $title_tosource}}
            {{if $plink}}
          </a>
          {{/if}}
          {{/if}}
        </h3>
        {{/if}}
      </div>
			<div
        class="wall-item-head{{if !$item.title && !$item.event && !$item.photo}} rounded-top{{/if}} clearfix">
        <div class="entry__meta">
          <div class="entry__meta-author">
            {{if $author_is_group_actor}}
            <svg width="24" height="24" viewBox="-3 -4 26 26" fill="none" class="bi bi-chat-quote">
              <path
                d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894zm-.493 3.905a21.682 21.682 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a9.68 9.68 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9.06 9.06 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105z" />
              <path
                d="M7.066 6.76A1.665 1.665 0 0 0 4 7.668a1.667 1.667 0 0 0 2.561 1.406c-.131.389-.375.804-.777 1.22a.417.417 0 0 0 .6.58c1.486-1.54 1.293-3.214.682-4.112zm4 0A1.665 1.665 0 0 0 8 7.668a1.667 1.667 0 0 0 2.561 1.406c-.131.389-.375.804-.777 1.22a.417.417 0 0 0 .6.58c1.486-1.54 1.293-3.214.682-4.112z" />
            </svg>
            {{/if}}

            <a href="{{$profile_url}}" title="{{$linktitle}}" data-bs-toggle="dropdown">{{$name}}</a>
            {{if $thread_author_menu}}
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
            <span class="autotime" title="{{$isotime}}"><time class="dt-published"
                datetime="{{$isotime}}">{{$localtime}}</time>{{if
              $expiretime}}&nbsp;{{$expiretime}}{{/if}}</span>
          </div>
          {{if $item.has_tags}}
          {{$item.mentions}} {{$item.tags}} {{$item.categories}} {{$item.folders}}
          {{/if}}
        </div>
      </div>

			{{if $body}}
			<div class="p-3 wall-item-content clearfix" id="pinned-item-content-{{$id}}">
				<div class="wall-item-body e-content" id="pinned-item-body-{{$id}}" >
					{{$body}}
				</div>
			</div>
			{{/if}}
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
