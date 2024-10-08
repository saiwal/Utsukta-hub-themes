{{if $item.comment_firstcollapsed}}
<div class="hide-comments-outer fakelink" onclick="showHideComments({{$item.id}});">
	<span id="hide-comments-{{$item.id}}" class="hide-comments">{{$item.hide_text}}</span>&nbsp;<span id="hide-comments-total-{{$item.id}}" class="hide-comments-total">{{$item.num_comments}}</span>
</div>
<div id="collapsed-comments-{{$item.id}}" class="collapsed-comments" style="display: none;">
{{/if}}
	<div id="thread-wrapper-{{$item.id}}" class="thread-wrapper{{if $item.toplevel}} {{$item.toplevel}} generic-content-wrapper h-entry {{else}} u-comment h-cite {{/if}}" data-b64mids='{{$item.mids}}'>
		<a name="item_{{$item.id}}" ></a>
		<div class="wall-item-outside-wrapper{{if $item.is_comment}} comment{{/if}}{{if $item.previewing}} preview{{/if}}" id="wall-item-outside-wrapper-{{$item.id}}" >
			<div class="card clearfix wall-item-content-wrapper{{if $item.is_comment}} comment{{/if}}" id="wall-item-content-wrapper-{{$item.id}}">
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
				<div class="p-2{{if $item.is_new}} bg-success text-white{{/if}} wall-item-title h3" id="wall-item-title-{{$item.id}}">
					{{if $item.title_tosource}}{{if $item.plink}}<a href="{{$item.plink.href}}" title="{{$item.title}} ({{$item.plink.title}})" rel="nofollow">{{/if}}{{/if}}{{$item.title}}{{if $item.title_tosource}}{{if $item.plink}}</a>{{/if}}{{/if}}
				</div>
				{{if ! $item.is_new}}
				<hr class="m-0">
				{{/if}}
				{{/if}}
				<div class="p-2 wall-item-head {{if $item.is_new && !$item.event && !$item.is_comment}} wall-item-head-new{{/if}}" >
					<div class="text-end float-right">
						<div class="wall-item-ago opacity-75" id="wall-item-ago-{{$item.id}}">
							{{if $item.editedtime}}
							<i class="fa fa-pencil"></i>
							{{/if}}
							{{if $item.delayed}}
							<i class="fa fa-clock-o"></i>
							{{/if}}
							{{if $item.location}}
							<small class="wall-item-location p-location" id="wall-item-location-{{$item.id}}">{{$item.location}}</small>
							{{/if}}
							{{if $item.verified}}
							<i class="fa fa-check text-success" title="{{$item.verified}}"></i>
							{{elseif $item.forged}}
							<i class="fa fa-exclamation text-danger" title="{{$item.forged}}"></i>
							{{/if}}
							<small class="autotime" title="{{$item.isotime}}"><time class="dt-published" datetime="{{$item.isotime}}">{{$item.localtime}}</time>{{if $item.editedtime}}&nbsp;{{$item.editedtime}}{{/if}}{{if $item.expiretime}}&nbsp;{{$item.expiretime}}{{/if}}</small>
						</div>
						{{if $item.thr_parent}}
						<a href="javascript:doscroll('{{$item.thr_parent}}',{{$item.parent}});" class="ms-3" title="{{$item.top_hint}}"><i class="fa fa-angle-double-up"></i></a>
						{{/if}}
						{{if $item.pinned}}
						<div class="wall-item-pinned" title="{{$item.pinned}}" id="wall-item-pinned-{{$item.id}}"><i class="fa fa-thumb-tack"></i></div>
						{{/if}}
					</div>
					<div class="float-left wall-item-info pr-2" id="wall-item-info-{{$item.id}}" >
						<div class="wall-item-photo-wrapper{{if $item.owner_url}} wwfrom{{/if}} h-card p-author" id="wall-item-photo-wrapper-{{$item.id}}">
							{{if $item.contact_id}}
							<div class="spinner-wrapper contact-edit-rotator contact-edit-rotator-{{$item.contact_id}}"><div class="spinner s"></div></div>
							{{/if}}
							<img src="{{$item.thumb}}" class="fakelink wall-item-photo{{$item.sparkle}} u-photo p-name" id="wall-item-photo-{{$item.id}}" alt="{{$item.name}}" loading="lazy" data-bs-toggle="dropdown" />
							{{if $item.author_is_group_actor}}
							<i class="fa fa-comments-o wall-item-photo-group-actor" title="{{$item.author_is_group_actor}}"></i>
							{{/if}}
							{{if $item.thread_author_menu}}
							<i class="fa fa-caret-down wall-item-photo-caret cursor-pointer" data-bs-toggle="dropdown"></i>
							<div class="dropdown-menu">
								{{foreach $item.thread_author_menu as $mitem}}
								<a class="dropdown-item{{if $mitem.class}} {{$mitem.class}}{{/if}}" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}}{{if $mitem.data}} {{$mitem.data}}{{/if}}>{{$mitem.title}}</a>
								{{/foreach}}
							</div>
							{{/if}}
						</div>
					</div>
					<div class="wall-item-author">
						{{if $item.lock}}
						<div class="float-left dropdown wall-item-lock">
							<i class="fa {{if $item.locktype == 2}}fa-envelope-o{{else if $item.locktype == 1}}fa-lock{{else}}fa-unlock{{/if}} lockview{{if $item.privacy_warning}} text-danger{{/if}}" data-bs-toggle="dropdown" title="{{$item.lock}}" onclick="lockview('item',{{$item.id}});" ></i>&nbsp;
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
				<div class="p-2 clearfix wall-item-tools">
					<div class="float-right wall-item-tools-right">
						{{if $item.toplevel && $item.emojis && $item.reactions}}
						<div class="btn-group">
							<button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="wall-item-react-{{$item.id}}">
								<i class="fa fa-smile-o"></i>
							</button>
							<div class="dropdown-menu dropdown-menu-end">
							{{foreach $item.reactions as $react}}
								<a class="dropdown-item clearfix" href="#" onclick="jotReact({{$item.id}},'{{$react}}'); return false;"><img class="menu-img-2" src="/images/emoji/{{$react}}.png" alt="{{$react}}" /></a>
							{{/foreach}}
							</div>
						</div>
						{{/if}}
						<div class="btn-group">
							{{if $item.like}}
                                <button type="button" title="{{$item.like.0}}" class="btn btn-secondary btn-sm" onclick="dolike({{$item.id}},'like'); return false;">
                                    <i class="fa fa-thumbs-o-up{{if $item.my_responses.like}} ivoted{{/if}}" ></i>
                                </button>
                                {{/if}}
                                {{if $item.dislike}}
                                <button type="button" title="{{$item.dislike.0}}" class="btn btn-secondary btn-sm" onclick="dolike({{$item.id}},'dislike'); return false;">
                                    <i class="fa fa-thumbs-o-down{{if $item.my_responses.dislike}} ivoted{{/if}}" ></i>
                                </button>
                                {{/if}}
                                {{if $item.isevent}}
                                <div class="btn-group">
                                    <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="wall-item-attend-menu-{{$item.id}}" title="{{$item.attend_title}}">
                                        <i class="fa fa-calendar-check-o"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" title="{{$item.attend.0}}" onclick="itemAddToCal({{$item.id}}); dolike({{$item.id}},'attendyes'); return false;">
                                            <i class="item-act-list fa fa-check{{if $item.my_responses.attend}} ivoted{{/if}}" ></i> {{$item.attend.0}}
                                        </a>
                                        <a class="dropdown-item" href="#" title="{{$item.attend.1}}" onclick="itemAddToCal({{$item.id}}), dolike({{$item.id}},'attendno'); return false;">
                                            <i class="item-act-list fa fa-times{{if $item.my_responses.attendno}} ivoted{{/if}}" ></i> {{$item.attend.1}}
                                        </a>
                                        <a class="dropdown-item" href="#" title="{{$item.attend.2}}" onclick="itemAddToCal({{$item.id}}); dolike({{$item.id}},'attendmaybe'); return false;">
                                            <i class="item-act-list fa fa-question{{if $item.my_responses.attendmaybe}} ivoted{{/if}}" ></i> {{$item.attend.2}}
                                        </a>
                                    </div>
                                </div>
                                {{/if}}
                                {{if $item.canvote}}
                                <div class="btn-group">
                                    <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="wall-item-consensus-menu-{{$item.id}}" title="{{$item.vote_title}}">
                                        <i class="fa fa-check-square-o"></i>
                                    </button>
                                    <div class="dropdown-menu" role="menu" aria-labelledby="wall-item-consensus-menu-{{$item.id}}">
                                        <a class="dropdown-item" href="#" title="{{$item.conlabels.0}}" onclick="dolike({{$item.id}},'agree'); return false;">
                                            <i class="item-act-list fa fa-check{{if $item.my_responses.agree}} ivoted{{/if}}" ></i> {{$item.conlabels.0}}
                                        </a>
                                        <a class="dropdown-item" href="#" title="{{$item.conlabels.1}}" onclick="dolike({{$item.id}},'disagree'); return false;">
                                             <i class="item-act-list fa fa-times{{if $item.my_responses.disagree}} ivoted{{/if}}" ></i> {{$item.conlabels.1}}
                                        </a>
                                        <a class="dropdown-item" href="#" title="{{$item.conlabels.2}}" onclick="dolike({{$item.id}},'abstain'); return false;">
                                            <i class="item-act-list fa fa-question{{if $item.my_responses.abstain}} ivoted{{/if}}" ></i> {{$item.conlabels.2}}
                                        </a>
                                    </div>
                                </div>
                                {{/if}}
                                <div class="btn-group">
                                    <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" id="wall-item-menu-{{$item.id}}">
                                        <i class="fa fa-cog"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" role="menu" aria-labelledby="wall-item-menu-{{$item.id}}">
                                        {{if $item.share}}
                                        <a class="dropdown-item" href="#" onclick="jotShare({{$item.id}},{{$item.item_type}}); return false"><i class="generic-icons-nav fa fa-fw fa-retweet" title="{{$item.share.0}}"></i>{{$item.share.0}}</a>
                                        {{/if}}
                                        {{if $item.embed}}
                                        <a class="dropdown-item" href="#" onclick="jotEmbed({{$item.id}},{{$item.item_type}}); return false"><i class="generic-icons-nav fa fa-fw fa-share" title="{{$item.embed.0}}"></i>{{$item.embed.0}}</a>
                                        {{/if}}
                                        {{if $item.plink}}
                                        <a class="dropdown-item" href="{{$item.plink.href}}" title="{{$item.plink.title}}" class="u-url"><i class="generic-icons-nav fa fa-fw fa-external-link"></i>{{$item.plink.title}}</a>
                                        {{/if}}
                                        {{if $item.edpost}}
                                        <a class="dropdown-item" href="{{$item.edpost.0}}" title="{{$item.edpost.1}}"><i class="generic-icons-nav fa fa-fw fa-pencil"></i>{{$item.edpost.1}}</a>
                                        {{/if}}
                                        {{if $item.tagger}}
                                        <a class="dropdown-item" href="#"  onclick="itemTag({{$item.id}}); return false;"><i id="tagger-{{$item.id}}" class="generic-icons-nav fa fa-fw fa-tag" title="{{$item.tagger.tagit}}"></i>{{$item.tagger.tagit}}</a>
                                        {{/if}}
                                        {{if $item.filer}}
                                        <a class="dropdown-item" href="#" onclick="itemFiler({{$item.id}}); return false;"><i id="filer-{{$item.id}}" class="generic-icons-nav fa fa-fw fa-folder-open" title="{{$item.filer}}"></i>{{$item.filer}}</a>
                                        {{/if}}
                                        {{if $item.bookmark}}
                                        <a class="dropdown-item" href="#" onclick="itemBookmark({{$item.id}}); return false;"><i id="bookmarker-{{$item.id}}" class="generic-icons-nav fa fa-fw fa-bookmark" title="{{$item.bookmark}}"></i>{{$item.bookmark}}</a>
                                        {{/if}}
                                        {{if $item.addtocal}}
                                        <a class="dropdown-item" href="#" onclick="itemAddToCal({{$item.id}}); return false;"><i id="addtocal-{{$item.id}}" class="generic-icons-nav fa fa-fw fa-calendar" title="{{$item.addtocal}}"></i>{{$item.addtocal}}</a>
                                        {{/if}}
                                        {{if $item.star}}
                                        <a class="dropdown-item" href="#" onclick="dostar({{$item.id}}); return false;"><i id="starred-{{$item.id}}" class="generic-icons-nav fa fa-fw{{if $item.star.isstarred}} starred fa-star{{else}} unstarred fa-star-o{{/if}}" title="{{$item.star.toggle}}"></i>{{$item.star.toggle}}</a>
                                        {{/if}}
                                        {{if $item.thread_action_menu}}
                                        {{foreach $item.thread_action_menu as $mitem}}
                                        <a class="dropdown-item" {{if $mitem.href}}href="{{$mitem.href}}"{{/if}} {{if $mitem.action}}onclick="{{$mitem.action}}"{{/if}} {{if $mitem.title}}title="{{$mitem.title}}"{{/if}} ><i class="generic-icons-nav fa fa-fw fa-{{$mitem.icon}}"></i>{{$mitem.title}}</a>
                                        {{/foreach}}
                                        {{/if}}
                                        {{if $item.drop.dropping}}
                                        <a class="dropdown-item" href="#" onclick="dropItem('item/drop/{{$item.id}}', '#thread-wrapper-{{$item.id}}', '{{$item.mid}}'); return false;" title="{{$item.drop.delete}}" ><i class="generic-icons-nav fa fa-fw fa-trash-o"></i>{{$item.drop.delete}}</a>
                                        {{/if}}
                                        {{if $item.edpost && $item.dreport}}
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="dreport/{{$item.mid}}">{{$item.dreport}}</a>
                                        {{/if}}
                                        {{if $item.settings}}
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item conversation-settings-link" href="" data-toggle="modal" data-target="#conversation_settings">{{$item.settings}}</a>
                                        {{/if}}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="like-rotator-{{$item.id}}" class="like-rotator"></div>
                        <div class="wall-item-tools-left btn-group"  id="wall-item-tools-left-{{$item.id}}">
                            {{if $item.star && $item.star.isstarred}}
                            <div class="btn-group" id="star-button-{{$item.id}}">
                                <button type="button" class="btn btn-secondary btn-sm wall-item-like" onclick="dostar({{$item.id}});"><i class="fa fa-star"></i></button>
                            </div>
                            {{/if}}
                            {{if $item.attachments}}
                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary btn-sm wall-item-like dropdown-toggle" data-bs-toggle="dropdown" id="attachment-menu-{{$item.id}}"><i class="fa fa-paperclip"></i></button>
                                <ul class="dropdown-menu" role="menu" aria-labelledby="attachment-menu-{{$item.id}}">{{$item.attachments}}</ul>
                            </div>
                            {{/if}}
                            <div class="wall-item-list-comments btn-group">
                                <a class="btn btn-secondary btn-sm border-0 wall-item-comments" href="{{$item.viewthread}}" title="{{$item.comment_count_txt.label}}{{if $item.list_unseen_txt}}, {{$item.list_unseen_txt.label}}{{/if}}">
                                    <i class="fa fa-comment-o"></i> {{$item.comment_count_txt.count}}{{if $item.unseen_comments}}<span class="unseen-wall-indicator-{{$item.id}}">, <i class="fa fa-eye-slash"></i> {{$item.list_unseen_txt.count}}</span>{{/if}}
                                </a>
                            </div>
                            {{if $item.unseen_comments}}
                            <div class="unseen-wall-indicator-{{$item.id}} btn-group">
                                <button class="btn btn-secondary btn-sm" title="{{$item.markseen}}" onclick="markItemRead({{$item.id}}); return false;">
                                    <i class="fa fa-check-square-o"></i>
                                </button>
                            </div>
                            {{/if}}
                            {{if $item.responses }}
                            {{foreach $item.responses as $verb=>$response}}
                            {{if $response.count}}
                            <div class="btn-group">
                            <button type="button" class="btn btn-secondary btn-sm border-0 wall-item-{{$response.button.class}}"{{if $response.modal}} data-bs-toggle="modal" data-bs-target="#{{$verb}}Modal-{{$item.id}}"{{else    }} data-bs-toggle="dropdown"{{/if}} id="wall-item-{{$verb}}-{{$item.id}}"><i class="fa fa-{{$response.button.icon}}"></i> {{$response.count}}</button>
                                {{if $response.modal}}
                                <div class="modal" id="{{$verb}}Modal-{{$item.id}}">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h4 class="modal-title">{{$response.count}} {{$response.button}}</h4>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                                            </div>
                                            <div class="modal-body response-list">
                                                <ul class="nav nav-pills flex-column">{{foreach $response.list as $liker}}<li class="nav-item">{{$liker}}</li>{{/foreach}}</ul>
                                            </div>
                                            <div class="modal-footer clear">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$item.modal_dismiss}}</button>
										</div>
									</div><!-- /.modal-content -->
								</div><!-- /.modal-dialog -->
							</div><!-- /.modal -->
							{{else}}
							<div class="dropdown-menu">{{foreach $response.list as $liker}}{{$liker}}{{/foreach}}</div>
							{{/if}}
						</div>
						{{/if}}
						{{/foreach}}
						{{/if}}
					</div>
				</div>
			</div>
		</div>
	</div>
{{if $item.comment_lastcollapsed}}
</div>
{{/if}}div class="card card-widget">
<div class="card-header">
<div class="user-block">
<img class="img-circle" src="../dist/img/user1-128x128.jpg" alt="User Image">
<span class="username"><a href="#">Jonathan Burke Jr.</a></span>
<span class="description">Shared publicly - 7:30 PM Today</span>
</div>

<div class="card-tools">
<button type="button" class="btn btn-tool" title="Mark as read">
<i class="far fa-circle"></i>
</button>
<button type="button" class="btn btn-tool" data-card-widget="collapse">
<i class="fas fa-minus"></i>
</button>
<button type="button" class="btn btn-tool" data-card-widget="remove">
<i class="fas fa-times"></i>
</button>
</div>

</div>
