{{if $item.toplevel}}
<article id="thread-wrapper-{{$item.id}}" class="brick entry thread-wrapper {{$item.toplevel}}"
	data-b64mids='{{$item.mids}}' data-animate-el>
	<a name="item_{{$item.id}}"></a>
	<div class="wall-item-outside-wrapper{{if $item.is_comment}} comment{{/if}}{{if $item.previewing}} preview{{/if}}"
		id="wall-item-outside-wrapper-{{$item.id}}">
		<div class="rounded wall-item-content-wrapper{{if $item.is_comment}} comment{{/if}}"
			id="wall-item-content-wrapper-{{$item.id}}">
			<div class="entry__header mb-3">
				{{if $item.photo}}
				<div class="wall-photo-item" id="wall-photo-item-{{$item.id}}">
					{{$item.photo}}
				</div>
				{{/if}}
				{{if $item.event}}
				<div class="wall-event-item border-bottom-0 entry__title" id="wall-event-item-{{$item.id}}">
					{{$item.event}}
				</div>
				{{/if}}
				{{if $item.title && $item.toplevel && !$item.event}}
				<h2 class="wall-item-title entry__title h1" id="wall-item-title-{{$item.id}}">
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
					<div class="wall-item-head{{if !$item.title && !$item.event && !$item.photo}} rounded-top{{/if}} clearfix">
						<div class="entry__meta">
							<ul>
								<li>{{if $item.author_is_group_actor}}
									<svg width="24" height="24" viewBox="-3 -4 26 26" fill="none" class="bi bi-chat-quote">
										<path
											d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894zm-.493 3.905a21.682 21.682 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a9.68 9.68 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9.06 9.06 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105z" />
										<path
											d="M7.066 6.76A1.665 1.665 0 0 0 4 7.668a1.667 1.667 0 0 0 2.561 1.406c-.131.389-.375.804-.777 1.22a.417.417 0 0 0 .6.58c1.486-1.54 1.293-3.214.682-4.112zm4 0A1.665 1.665 0 0 0 8 7.668a1.667 1.667 0 0 0 2.561 1.406c-.131.389-.375.804-.777 1.22a.417.417 0 0 0 .6.58c1.486-1.54 1.293-3.214.682-4.112z" />
									</svg>
									{{/if}}

									<a href="{{$item.profile_url}}" title="{{$item.author_id}}"
										data-bs-toggle="dropdown">{{$item.name}}</a>
									{{if $item.thread_author_menu}}
									<i class="bi bi-caret-down-fill wall-item-photo-caret cursor-pointer" data-bs-toggle="dropdown"></i>
									<div class="dropdown-menu">
										{{foreach $item.thread_author_menu as $mitem}}
										<a class="dropdown-item{{if $mitem.class}} {{$mitem.class}}{{/if}}" {{if
											$mitem.href}}href="{{$mitem.href}}" {{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"
											{{/if}}{{if $mitem.title}}title="{{$mitem.title}}" {{/if}}{{if
											$mitem.data}}{{$mitem.data}}{{/if}}>{{$mitem.title}}</a>
										{{/foreach}}
									</div>
									{{/if}}
								</li>
								<li> <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
										<circle cx="12" cy="12" r="7.25" stroke="currentColor" stroke-width="1.5"></circle>
										<path stroke="currentColor" stroke-width="1.5" d="M12 8V12L14 14"></path>
									</svg>
									<span class="autotime" title="{{$item.isotime}}"><time class="dt-published"
											datetime="{{$item.isotime}}">{{$item.localtime}}</time>{{if
										$item.expiretime}}&nbsp;{{$item.expiretime}}{{/if}}</span>
								</li>
								{{if $item.has_tags}}<li>
									{{$item.mentions}} {{$item.tags}} {{$item.categories}} {{$item.folders}}
								</li>
								{{/if}}
							</ul>

						</div>
					</div>
			</div>

			{{if $item.divider}}
			<hr class="wall-item-divider">
			{{/if}}
			{{if $item.body}}
			<div class="wall-item-content clearfix" id="wall-item-content-{{$item.id}}">
				<div class="wall-item-body e-content" id="wall-item-body-{{$item.id}}" {{if $item.rtl}} dir="rtl" {{/if}}>
					{{$item.body}}
				</div>
			</div>
			{{/if}}
		</div>
	</div>
	{{if $item.thread_level == 1}}
	{{if $item.toplevel && $item.load_more && $item.threaded}}
	<div id="load-more-progress-wrapper-{{$item.id}}" class="progress{{if $item.blog_mode}} d-none{{/if}}"
		role="progressbar" aria-valuenow="{{$item.comments_total_percent}}" aria-valuemin="0" aria-valuemax="100"
		style="height: 1px">
		<div id="load-more-progress-{{$item.id}}" class="progress-bar bg-info"
			style="width: {{$item.comments_total_percent}}%; margin-left: auto; margin-right: auto;"
			data-comments-total="{{$item.comments_total}}">
		</div>
	</div>
	<div id="load-more-{{$item.id}}"
		class="load-more text-center text-secondary cursor-pointer{{if $item.blog_mode}} d-none{{/if}}"
		title="{{$item.load_more_title}}"
		onclick="request(0, '{{$item.rawmid}}', 'load', {{$item.parent}}, ''); return false;">
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
	<div id="wall-item-comment-wrapper-{{$item.id}}"
		class="p-2 rounded wall-item-comment-wrapper{{if $item.children}} wall-item-comment-wrapper-wc{{/if}}{{if $item.comment_hidden}} d-none{{/if}}">
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
</article>
{{else}}

{{/if}}
