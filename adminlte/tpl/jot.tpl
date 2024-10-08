<!-- Controls appearance of input form/comments/editors -->
<input id="invisible-wall-file-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50;left:-50;width:0;height:0;" multiple>
<input id="invisible-comment-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50;left:-50;width:0;height:0;" multiple>
<form id="profile-jot-form" action="{{$action}}" method="post" class="acl-form" data-form_id="profile-jot-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}' data-bang='{{$bang}}'>
	{{$mimeselect}}
	{{$layoutselect}}
	{{if $id_select}}
	<div class="channel-id-select-div">
		<span class="channel-id-select-desc">{{$id_seltext}}</span> {{$id_select}}
	</div>
	{{/if}}
	<div class="mb-4" id="profile-jot-wrapper">

		{{if $parent}}
		<input type="hidden" name="parent" value="{{$parent}}" />
		{{/if}}
		<input type="hidden" name="obj_type" value="{{$ptyp}}" />
		<input type="hidden" name="profile_uid" value="{{$profile_uid}}" />
		<input type="hidden" name="return" value="{{$return_path}}" />
		<input type="hidden" name="location" id="jot-location" value="{{$defloc}}" />
		<input type="hidden" name="expire" id="jot-expire" value="{{$defexpire}}" />
		<input type="hidden" name="created" id="jot-created" value="{{$defpublish}}" />
		<input type="hidden" name="media_str" id="jot-media" value="" />
		<input type="hidden" name="source" id="jot-source" value="{{$source}}" />
		<input type="hidden" name="coord" id="jot-coord" value="" />
		<input type="hidden" id="jot-postid" name="post_id" value="{{$post_id}}" />
		<input type="hidden" id="jot-webpage" name="webpage" value="{{$webpage}}" />
		<input type="hidden" name="preview" id="jot-preview" value="0" />
		<input type="hidden" id="jot-consensus" name="consensus" value="{{if $consensus}}{{$consensus}}{{else}}0{{/if}}" />
		<input type="hidden" id="jot-nocomment" name="nocomment" value="{{if $nocomment}}{{$nocomment}}{{else}}0{{/if}}" />


		<div id="jot-title-wrap" class="jothidden border-0">
			<input class="w-100 border-2 rounded-top form-control" name="title" id="jot-title" type="text" placeholder="{{$placeholdertitle}}" tabindex="1" value="{{$title}}">
		</div>
		<div id="jot-summary-wrap" class="jothidden border-0">
			<input class="w-100 border-2 form-control" name="summary" id="jot-summary" type="text" placeholder="{{$placeholdersummary}}" tabindex="2" value="{{$summary}}">
		</div>
		{{if $catsenabled}}
		<div id="jot-category-wrap" class="jothidden border-0">
			<input class="w-100 border-2 form-control" name="category" id="jot-category" type="text" placeholder="{{$placeholdercategory}}" value="{{$category}}" data-role="cat-tagsinput">
		</div>
		{{/if}}
		{{if $webpage}}
		<div id="jot-pagetitle-wrap" class="jothidden border-0">
			<input class="w-100 border-2 rounded-top form-control" name="pagetitle" id="jot-pagetitle" type="text" placeholder="{{$placeholdpagetitle}}" value="{{$pagetitle}}">
		</div>
		{{/if}}
		{{if $customjotheaders}}
		<div id="jot-customjotheaders-wrap" class="jothidden border-0">
			{{$customjotheaders}}
		</div>
		{{/if}}
		<div id="jot-text-wrap">
			<div id="profile-jot-tools" class="btn-group d-none">
				{{if $is_owner}}
				<a id="profile-jot-settings" class="btn btn-outline-secondary btn-sm border-2" href="/settings/editor/?f=&rpath=/{{$return_path}}" tabindex="4"><i class="fa fa-cog"></i></a>
				{{/if}}
				{{if $reset}}
				<button type="button" id="profile-jot-reset" class="btn btn-outline-secondary btn-sm border-2" title="{{$reset}}" tabindex="-1" onclick="itemCancel(); return false;">
					<i class="fa fa-close"></i>
				</button>
				{{/if}}
			</div>
			<textarea class="profile-jot-text border-2 rounded form-control" id="profile-jot-text" name="body" tabindex="2" placeholder="{{$placeholdtext}}" tabindex="3">{{$content}}</textarea>
		</div>
		{{if $attachment}}
		<div id="jot-attachment-wrap">
			<input class="jot-attachment form-control" name="attachment" id="jot-attachment" type="text" value="{{$attachment}}" readonly="readonly" onclick="this.select();">
		</div>
		{{/if}}
		<div id="jot-poll-wrap" class="p-2 d-none">
			<div id="jot-poll-options">
				<div class="jot-poll-option mb-3">
					<input class="w-100 border-2 form-control" name="poll_answers[]" type="text" value="" placeholder="{{$poll_option_label}}">
				</div>
				<div class="jot-poll-option mb-3">
					<input class="w-100 border-2 form-control" name="poll_answers[]" type="text" value="" placeholder="{{$poll_option_label}}">
				</div>
			</div>
			{{include file="field_checkbox.tpl" field=$multiple_answers}}
			<div id="jot-poll-tools" class="clearfix">
				<div id="poll-tools-right" class="float-right">
					<div class="input-group">
						<input type="text" name="poll_expire_value" class="form-control" value="10" size="3">
						<select class="form-control" id="duration-select" name="poll_expire_unit">
							<option value="Minutes">{{$poll_expire_unit_label.0}}</option>
							<option value="Hours">{{$poll_expire_unit_label.1}}</option>
							<option value="Days" selected="selected">{{$poll_expire_unit_label.2}}</option>
						</select>
					</div>
				</div>

				<div id="poll-tools-left" class="float-start">
					<button id="jot-add-option" class="btn btn-outline-secondary btn-sm" type="button">
						<i class="fa fa-plus"></i> {{$poll_add_option_label}}
					</button>
				</div>
			</div>
		</div>
		<div id="profile-jot-submit-wrapper" class="clearfix p-2 jothidden border-0">
             <div id="profile-jot-submit-right" class="btn-group float-right">
				{{foreach $customsubmitright as $csr}}
				<button type="button" class="btn btn-outline-secondary btn-sm" {{$csr.buttonparams}} title="{{$csr.preview}}">
					{{$csr.buttoncontent}}
				</button>
				{{/foreach}}
				{{if $preview}}
				<button type="button" class="btn btn-outline-secondary btn-sm" onclick="preview_post();return false;" title="{{$preview}}">
					<i class="fa fa-eye jot-icons" ></i>
				</button>
				{{/if}}
				{{if $jotnets}}
				<button type="button" id="dbtn-jotnets" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#jotnetsModal" type="button" title="{{$jotnets_label}}" style="{{if $lockstate == 'lock'}}display: none;{{/if}}">
					<i class="fa fa-share-alt jot-icons"></i>
				</button>
				{{/if}}
				{{if $showacl}}
				<button type="button" id="dbtn-acl" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aclModal" title="{{$permset}}" type="button" data-form_id="profile-jot-form">
					<i id="jot-perms-icon" class="fa fa-{{$lockstate}} jot-icons{{if $bang}} jot-lock-warn{{/if}}"></i>
				</button>
				{{/if}}
				<button id="dbtn-submit" class="btn btn-primary btn-sm" type="submit" tabindex="3" name="button-submit">{{$share}}</button>
			</div>

			<div id="profile-jot-submit-left" class="btn-toolbar float-start">
				{{if $bbcode}}
				<div class="btn-group me-2">
					<button type="button" id="main-editor-bold" class="btn btn-outline-secondary btn-sm" title="{{$bold}}" onclick="inserteditortag('b', 'profile-jot-text'); return false;">
						<i class="fa fa-bold jot-icons"></i>
					</button>
					<button type="button" id="main-editor-italic" class="btn btn-outline-secondary btn-sm" title="{{$italic}}" onclick="inserteditortag('i', 'profile-jot-text'); return false;">
						<i class="fa fa-italic jot-icons"></i>
					</button>
					<button type="button" id="main-editor-underline" class="btn btn-outline-secondary btn-sm" title="{{$underline}}" onclick="inserteditortag('u', 'profile-jot-text'); return false;">
						<i class="fa fa-underline jot-icons"></i>
					</button>
					<button type="button" id="main-editor-quote" class="btn btn-outline-secondary btn-sm" title="{{$quote}}" onclick="inserteditortag('quote', 'profile-jot-text'); return false;">
						<i class="fa fa-quote-left jot-icons"></i>
					</button>
					<button type="button" id="main-editor-code" class="btn btn-outline-secondary btn-sm" title="{{$code}}" onclick="inserteditortag('code', 'profile-jot-text'); return false;">
						<i class="fa fa-terminal jot-icons"></i>
					</button>
				</div>
				{{/if}}
				{{if $visitor}}
				<div class="btn-group me-2 d-none d-lg-flex">
					{{if $writefiles}}
					<button type="button" id="wall-file-upload" class="btn btn-outline-secondary btn-sm" title="{{$attach}}" >
						<i id="wall-file-upload-icon" class="fa fa-paperclip jot-icons"></i>
					</button>
					{{/if}}
					{{if $weblink}}
					<button type="button" id="profile-link-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$weblink}}" ondragenter="linkdropper(event);" ondragover="linkdropper(event);" ondrop="linkdrop(event);"  onclick="jotGetLink(); return false;">
						<i id="profile-link" class="fa fa-link jot-icons"></i>
					</button>
					{{/if}}
					{{if $embedPhotos}}
					<button type="button" id="embed-photo-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$embedPhotos}}" onclick="initializeEmbedPhotoDialog();return false;">
						<i id="embed-photo" class="fa fa-file-image-o jot-icons"></i>
					</button>
					{{/if}}
				</div>
				<div class="btn-group me-2 d-none d-lg-flex">
					{{if $setloc}}
					<button type="button" id="profile-location-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$setloc}}" onclick="jotGetLocation();return false;">
						<i id="profile-location" class="fa fa-globe jot-icons"></i>
					</button>
					{{/if}}
					{{if $clearloc}}
					<button type="button" id="profile-nolocation-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$clearloc}}" onclick="jotClearLocation();return false;" disabled="disabled">
						<i id="profile-nolocation" class="fa fa-circle-o jot-icons"></i>
					</button>
					{{/if}}
				{{else}}
				<div class="btn-group d-none d-lg-flex">
				{{/if}}
				{{if $feature_expire}}
					<button type="button" id="profile-expire-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$expires}}" onclick="jotGetExpiry();return false;">
						<i id="profile-expires" class="fa fa-eraser jot-icons"></i>
					</button>
				{{/if}}
				{{if $feature_future}}
					<button type="button" id="profile-future-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$future_txt}}" onclick="jotGetPubDate();return false;">
						<i id="profile-future" class="fa fa-clock-o jot-icons"></i>
					</button>
				{{/if}}
				{{if $feature_encrypt}}
					<button type="button" id="profile-encrypt-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$encrypt}}" onclick="hz_encrypt('{{$cipher}}','#profile-jot-text');return false;">
						<i id="profile-encrypt" class="fa fa-key jot-icons"></i>
					</button>
				{{/if}}
					<button type="button" id="profile-poll-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$poll}}" onclick="initPoll();">
						<i id="profile-poll" class="fa fa-bar-chart jot-icons"></i>
					</button>
				{{if $feature_nocomment}}
					<button type="button" id="profile-nocomment-wrapper" class="btn btn-outline-secondary btn-sm" title="{{$nocommenttitle}}" onclick="toggleNoComment();return false;">
						<i id="profile-nocomment" class="fa fa-comments jot-icons"></i>
					</button>
				{{/if}}
				{{if $custommoretoolsbuttons}}
					{{$custommoretoolsbuttons}}
				{{/if}}
				</div>
				{{if $writefiles || $weblink || $setloc || $clearloc || $feature_expire || $feature_encrypt || $custommoretoolsdropdown}}
				<div class="btn-group d-lg-none">
					<button type="button" id="more-tools" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
						<i id="more-tools-icon" class="fa fa-cog jot-icons"></i>
					</button>
					<div class="dropdown-menu">
						{{if $visitor}}
						{{if $writefiles}}
						<a class="dropdown-item" id="wall-file-upload-sub" href="#" ><i class="fa fa-paperclip"></i>&nbsp;{{$attach}}</a>
						{{/if}}
						{{if $weblink}}
						<a class="dropdown-item" href="#" onclick="jotGetLink(); return false;"><i class="fa fa-link"></i>&nbsp;{{$weblink}}</a>
						{{/if}}
						{{if $embedPhotos}}
						<a class="dropdown-item" href="#" onclick="initializeEmbedPhotoDialog(); return false;"><i class="fa fa-file-image-o jot-icons"></i>&nbsp;{{$embedPhotos}}</a>
						{{/if}}
						{{if $setloc}}
						<a class="dropdown-item" href="#" onclick="jotGetLocation(); return false;"><i class="fa fa-globe"></i>&nbsp;{{$setloc}}</a>
						{{/if}}
						{{if $clearloc}}
						<a class="dropdown-item" href="#" onclick="jotClearLocation(); return false;"><i class="fa fa-circle-o"></i>&nbsp;{{$clearloc}}</a>
						{{/if}}
						{{/if}}
						{{if $feature_expire}}
						<a class="dropdown-item" href="#" onclick="jotGetExpiry(); return false;"><i class="fa fa-eraser"></i>&nbsp;{{$expires}}</a>
						{{/if}}
						{{if $feature_future}}
						<a class="dropdown-item" href="#" onclick="jotGetPubDate();return false;"><i class="fa fa-clock-o"></i>&nbsp;{{$future_txt}}</a>
						{{/if}}
						{{if $feature_encrypt}}
						<a class="dropdown-item" href="#" onclick="hz_encrypt('{{$cipher}}','#profile-jot-text');return false;"><i class="fa fa-key"></i>&nbsp;{{$encrypt}}</a>
						{{/if}}
						<a class="dropdown-item" href="#" onclick="initPoll(); return false"><i id="profile-poll" class="fa fa-bar-chart jot-icons"></i>&nbsp;{{$poll}}</a>
						{{if $feature_nocomment}}
						<a class="dropdown-item" href="#" onclick="toggleNoComment(); return false;"><i id="profile-nocomment-sub" class="fa fa-comments"></i>&nbsp;{{$nocommenttitlesub}}</a>
						{{/if}}
						<hr />
						{{$custommoretoolsdropdown}}
					</div>
				</div>
				{{/if}}
				<div class="btn-group">
					<div id="profile-rotator" class="mt-2 spinner-wrapper">
						<div class="spinner s"></div>
					</div>
				</div>
			</div>
			<div class="clear"></div>
			{{if $jotplugins}}
			<div id="profile-jot-plugin-wrapper" class="mt-2">
				{{$jotplugins}}
			</div>
			{{/if}}
			{{if $jotnets}}
			<div class="modal" id="jotnetsModal" tabindex="-1" role="dialog" aria-labelledby="jotnetsModalLabel" aria-hidden="true">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<h3 class="modal-title" id="expiryModalLabel">{{$jotnets_label}}</h3>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true">&times;</button>
						</div>
						<div class="modal-body">
							{{$jotnets}}
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div><!-- /.modal-content -->
				</div><!-- /.modal-dialog -->
			</div><!-- /.modal -->
			{{/if}}
		</div>
	</div>
</form>

<div id="jot-preview-content" style="display:none;"></div>

{{$acl}}

{{if $feature_expire}}
<!-- Modal for item expiry-->
<div class="modal" id="expiryModal" tabindex="-1" role="dialog" aria-labelledby="expiryModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="expiryModalLabel">{{$expires}}</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
			</div>
			<div class="modal-body mb-3" style="width:90%">
				<div class="date">
					<input type="text" placeholder="yyyy-mm-dd HH:MM" name="start_text" id="expiration-date" class="form-control" />
				</div>
				<script>
					$(function () {
						var picker = $('#expiration-date').datetimepicker({format:'Y-m-d H:i', minDate: 0 });
					});
				</script>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$expiryModalCANCEL}}</button>
				<button id="expiry-modal-OKButton" type="button" class="btn btn-primary">{{$expiryModalOK}}</button>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{/if}}

{{if $feature_future}}
<div class="modal" id="createdModal" tabindex="-1" role="dialog" aria-labelledby="createdModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="createdModalLabel">{{$future_txt}}</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
			</div>
			<div class="modal-body mb-3" style="width:90%">
				<div class="date">
					<input type="text" placeholder="yyyy-mm-dd HH:MM" name="created_text" id="created-date" class="form-control" />
				</div>
				<script>
					$(function () {
						var picker = $('#created-date').datetimepicker({format:'Y-m-d H:i', minDate: 0 });
					});
				</script>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$expiryModalCANCEL}}</button>
				<button id="created-modal-OKButton" type="button" class="btn btn-primary">{{$expiryModalOK}}</button>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{/if}}

{{if $embedPhotos}}
<div class="modal" id="embedPhotoModal" tabindex="-1" role="dialog" aria-labelledby="embedPhotoLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="embedPhotoModalLabel">{{$embedPhotosModalTitle}}</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
			</div>
			<div class="modal-body" id="embedPhotoModalBody" >
				<div id="embedPhotoModalBodyAlbumListDialog" class="d-none">
					<div id="embedPhotoModalBodyAlbumList"></div>
				</div>
				<div id="embedPhotoModalBodyAlbumDialog" class="d-none"></div>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{/if}}

{{if $content || $attachment || $expanded}}
<script>initEditor();</script>
{{/if}}
