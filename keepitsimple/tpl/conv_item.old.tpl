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
								<li>{{if $item.author_is_group_actor}}<svg width="24px" height="24px" viewBox="-3 -4 26 26" id="forum-16px" stroke="currentColor" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg">
  <path id="Path_101" data-name="Path 101" d="M-7.5,16a.48.48,0,0,1-.158-.026L-10,15.193A5.971,5.971,0,0,1-13,16a6.006,6.006,0,0,1-6-6,6.006,6.006,0,0,1,6-6,6.006,6.006,0,0,1,6,6,5.976,5.976,0,0,1-.807,3l.782,2.345a.5.5,0,0,1-.121.512A.5.5,0,0,1-7.5,16ZM-13,5a5.006,5.006,0,0,0-5,5,5.006,5.006,0,0,0,5,5,4.984,4.984,0,0,0,2.668-.777.5.5,0,0,1,.426-.052l1.616.538-.539-1.615a.5.5,0,0,1,.052-.426A4.982,4.982,0,0,0-8,10,5.006,5.006,0,0,0-13,5Zm-9.342,7.974,3-1a.5.5,0,0,0,.317-.632.5.5,0,0,0-.633-.316l-2.051.683L-20.94,9.4a.5.5,0,0,0-.073-.454,4.96,4.96,0,0,1,.478-6.485A4.966,4.966,0,0,1-17,1a4.966,4.966,0,0,1,3.535,1.464.5.5,0,0,0,.707,0,.5.5,0,0,0,0-.707A5.959,5.959,0,0,0-17,0a5.959,5.959,0,0,0-4.242,1.757,5.948,5.948,0,0,0-.727,7.569l-1.006,3.016a.5.5,0,0,0,.121.512A.5.5,0,0,0-22.5,13,.48.48,0,0,0-22.342,12.974Z" transform="translate(23)"/>
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
