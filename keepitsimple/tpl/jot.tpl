{{if $popup}}
<div id="jot-popup" style="display:none">
{{/if}}

<input id="invisible-wall-file-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50;left:-50;width:0;height:0;" multiple>
<input id="invisible-comment-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50;left:-50;width:0;height:0;" multiple>
<form id="profile-jot-form" action="{{$action}}" method="post" class="acl-form mb-3" data-form_id="profile-jot-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}' data-bang='{{$bang}}'>
	{{$mimeselect}}
	{{$layoutselect}}
	{{if $id_select}}
	<div class="channel-id-select-div">
		<span class="channel-id-select-desc">{{$id_seltext}}</span> {{$id_select}}
	</div>
	{{/if}}
	<div class="mb-0" id="profile-jot-wrapper">

		{{if $parent}}
		<input type="hidden" name="parent" value="{{$parent}}" />
		{{/if}}
		<input type="hidden" id="jot-delayed" name="delayed" value="0" />
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
			<input class="w-100" name="title" id="jot-title" type="text" placeholder="{{$placeholdertitle}}" tabindex="1" value="{{$title}}">
		</div>
		<div id="jot-summary-wrap" class="jothidden border-0">
            <textarea class="w-100" name="summary" id="jot-summary" type="text" placeholder="{{$placeholdersummary}}" tabindex="2" value="{{$summary}}">{{$summary}}</textarea>
		</div>
		{{if $catsenabled}}
		<div id="jot-category-wrap" class="jothidden border-0">
			<input class="w-100" name="category" id="jot-category" type="text" placeholder="{{$placeholdercategory}}" value="{{$category}}" data-role="cat-tagsinput">
		</div>
		{{/if}}
		{{if $webpage}}
		<div id="jot-pagetitle-wrap" class="jothidden border-0">
			<input class="w-100 " name="pagetitle" id="jot-pagetitle" type="text" placeholder="{{$placeholdpagetitle}}" value="{{$pagetitle}}">
		</div>
		{{/if}}
		{{if $customjotheaders}}
		<div id="jot-customjotheaders-wrap" class="jothidden">
			{{$customjotheaders}}
		</div>
		{{/if}}
		<div id="jot-text-wrap" class="border-0">
			<div id="profile-jot-tools" class="btn-group p-2 d-none">
				{{if $is_owner}}
				<a id="profile-jot-settings" class="pe-3" href="/settings/editor/?f=&rpath=/{{$return_path}}" tabindex="4"><i class="bi bi-gear"></i></a>
				{{/if}}
				{{if $reset}}
				<a type="button" id="profile-jot-reset" class="pe-3" title="{{$reset}}" tabindex="-1" onclick="itemCancel(); return false;">
					<i class="bi bi-x-lg"></i>
				</a>
				{{/if}}
			</div>
			<textarea class="profile-jot-text border-1 mb-0" id="profile-jot-text" name="body" tabindex="2" placeholder="{{$placeholdtext}}" tabindex="3" type="text">{{$content}}</textarea>
		</div>
		{{if $attachment}}
		<div id="jot-attachment-wrap">
			<input class="jot-attachment" name="attachment" id="jot-attachment" type="text" value="{{$attachment}}" readonly="readonly" onclick="this.select();">
		</div>
		{{/if}}
		<div id="jot-poll-wrap" class="pt-3 d-none">
			<div id="jot-poll-options">
				<div class="jot-poll-option mb-3">
					<input class="w-100 " name="poll_answers[]" type="text" value="" placeholder="{{$poll_option_label}}">
				</div>
				<div class="jot-poll-option mb-3">
					<input class="w-100 " name="poll_answers[]" type="text" value="" placeholder="{{$poll_option_label}}">
				</div>
			</div>
			{{include file="field_checkbox.tpl" field=$multiple_answers}}
			<div id="jot-poll-tools" class="clearfix">
				<div id="poll-tools-left" class="d-flex">
					<a id="jot-add-option" class="pe-3" type="button">
						<i class="bi bi-plus-lg"></i> {{$poll_add_option_label}}
					</a>
				</div>
				<div id="poll-tools-right" class="float-end">
					<div class="input-group">
						<input type="text" name="poll_expire_value" class="" value="10" size="3">
						<select class="" id="duration-select" name="poll_expire_unit">
							<option value="Minutes">{{$poll_expire_unit_label.0}}</option>
							<option value="Hours">{{$poll_expire_unit_label.1}}</option>
							<option value="Days" selected="selected">{{$poll_expire_unit_label.2}}</option>
						</select>
					</div>
				</div>
			</div>
		</div>
		<div id="profile-jot-submit-wrapper" class="clearfix pt-2 jothidden border-0">
			<div id="profile-jot-submit-left" class="d-flex justify-content-center mb-3">
				{{if $bbcode}}
				<div class="btn-group me-2">
					<a type="button" id="main-editor-bold" class="pe-3 border-0" title="{{$bold}}" onclick="inserteditortag('b', 'profile-jot-text'); return false;">
						<i class="bi bi-type-bold jot-icons"></i>
					</a>
					<a type="button" id="main-editor-italic" class="pe-3 border-0" title="{{$italic}}" onclick="inserteditortag('i', 'profile-jot-text'); return false;">
						<i class="bi bi-type-italic jot-icons"></i>
					</a>
					<a type="button" id="main-editor-underline" class="pe-3 border-0" title="{{$underline}}" onclick="inserteditortag('u', 'profile-jot-text'); return false;">
						<i class="bi bi-type-underline jot-icons"></i>
					</a>
					<a type="button" id="main-editor-quote" class="pe-3 border-0" title="{{$quote}}" onclick="inserteditortag('quote', 'profile-jot-text'); return false;">
						<i class="bi bi-quote jot-icons"></i>
					</a>
					<a type="button" id="main-editor-code" class="pe-3 border-0" title="{{$code}}" onclick="inserteditortag('code', 'profile-jot-text'); return false;">
						<i class="bi bi-code jot-icons"></i>
					</a>
					<a type="button" id="main-editor-bold" class="pe-3 border-0" title="{{$highlighter}}" onclick="inserteditortag('mark', 'profile-jot-text'); return false;">
						<i class="bi bi-highlighter jot-icons"></i>
					</a>
				</div>
				{{/if}}
				{{if $visitor}}
				<div class="btn-group me-2 d-none d-lg-flex">
					{{if $writefiles}}
					<a type="button" id="wall-file-upload" class="pe-3 border-0" title="{{$attach}}" >
						<i id="wall-file-upload-icon" class="bi bi-paperclip jot-icons"></i>
					</a>
					{{/if}}
					{{if $embedPhotos}}
					<a type="button" id="embed-photo-wrapper" class="pe-3 border-0" title="{{$embedPhotos}}" onclick="initializeEmbedPhotoDialog();return false;">
						<i id="embed-photo" class="bi bi-file-image jot-icons"></i>
					</a>
					{{/if}}
					{{if $weblink}}
					<a type="button" id="profile-link-wrapper" class="pe-3 border-0" title="{{$weblink}}" ondragenter="linkdropper(event);" ondragover="linkdropper(event);" ondrop="linkdrop(event);"  onclick="jotGetLink(); return false;">
						<i id="profile-link" class="bi bi-link-45deg jot-icons"></i>
					</a>
					{{/if}}
				</div>
				<div class="btn-group me-2 d-none d-lg-flex">
					{{if $setloc}}
					<a type="button" id="profile-location-wrapper" class="pe-3 border-0" title="{{$setloc}}" onclick="jotGetLocation();return false;">
						<i id="profile-location" class="bi bi-geo-alt-fill jot-icons"></i>
					</a>
					{{/if}}
					{{if $clearloc}}
					<a type="button" id="profile-nolocation-wrapper" class="pe-3 border-0" title="{{$clearloc}}" onclick="jotClearLocation();return false;" disabled="disabled">
						<i id="profile-nolocation" class="bi bi-geo-alt jot-icons"></i>
					</a>
					{{/if}}
				{{else}}
				<div class="btn-group d-none d-lg-flex">
				{{/if}}
				{{if $feature_expire}}
					<a type="button" id="profile-expire-wrapper" class="pe-3 border-0" title="{{$expires}}" onclick="jotGetExpiry();return false;">
						<i id="profile-expires" class="bi bi-eraser jot-icons"></i>
					</a>
				{{/if}}
				{{if $feature_future}}
					<a type="button" id="profile-future-wrapper" class="pe-3 border-0" title="{{$future_txt}}" onclick="jotGetPubDate();return false;">
						<i id="profile-future" class="bi bi-clock jot-icons"></i>
					</a>
				{{/if}}
				{{if $feature_encrypt}}
					<a type="button" id="profile-encrypt-wrapper" class="pe-3 border-0" title="{{$encrypt}}" onclick="sodium_encrypt('#profile-jot-text'); return false;">
						<i id="profile-encrypt" class="bi bi-key jot-icons"></i>
					</a>
				{{/if}}
					<a type="button" id="profile-poll-wrapper" class="pe-3 border-0" title="{{$poll}}" onclick="initPoll();">
						<i id="profile-poll" class="bi bi-bar-chart jot-icons"></i>
					</a>
				{{if $feature_nocomment}}
					<a type="button" id="profile-nocomment-wrapper" class="pe-3 border-0" title="{{$nocommenttitle}}" onclick="toggleNoComment();return false;">
						<i id="profile-nocomment" class="bi{{if $nocomment}} bi-chat{{else}} bi-chat-dots{{/if}} jot-icons"></i>
					</a>
				{{/if}}
				{{if $custommoretoolsbuttons}}
					{{$custommoretoolsbuttons}}
				{{/if}}
				</div>
				{{if $writefiles || $weblink || $setloc || $clearloc || $feature_expire || $feature_encrypt || $custommoretoolsdropdown}}
				<div class="btn-group d-lg-none">
					<a type="button" id="more-tools" class="pe-3 border-0 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
						<i id="more-tools-icon" class="bi bi-gear jot-icons"></i>
					</a>
					<div class="dropdown-menu">
						{{if $visitor}}
						{{if $writefiles}}
						<a class="dropdown-item" id="wall-file-upload-sub" href="#" ><i class="bi bi-paperclip"></i>&nbsp;{{$attach}}</a>
						{{/if}}
						{{if $embedPhotos}}
						<a class="dropdown-item" href="#" onclick="initializeEmbedPhotoDialog(); return false;"><i class="bi bi-file-image jot-icons"></i>&nbsp;{{$embedPhotos}}</a>
						{{/if}}
						{{if $weblink}}
						<a class="dropdown-item" href="#" onclick="jotGetLink(); return false;"><i class="bi bi-link-45deg"></i>&nbsp;{{$weblink}}</a>
						{{/if}}
						{{if $setloc}}
						<a class="dropdown-item" href="#" onclick="jotGetLocation(); return false;"><i class="bi bi-geo-alt-fill"></i>&nbsp;{{$setloc}}</a>
						{{/if}}
						{{if $clearloc}}
						<a class="dropdown-item" href="#" onclick="jotClearLocation(); return false;"><i class="bi bi-geo-alt"></i>&nbsp;{{$clearloc}}</a>
						{{/if}}
						{{/if}}
						{{if $feature_expire}}
						<a class="dropdown-item" href="#" onclick="jotGetExpiry(); return false;"><i class="bi bi-eraser"></i>&nbsp;{{$expires}}</a>
						{{/if}}
						{{if $feature_future}}
						<a class="dropdown-item" href="#" onclick="jotGetPubDate();return false;"><i class="bi bi-clock"></i>&nbsp;{{$future_txt}}</a>
						{{/if}}
						{{if $feature_encrypt}}
						<a class="dropdown-item" href="#" onclick="sodium_encrypt('#profile-jot-text'); return false;"><i class="bi bi-key"></i>&nbsp;{{$encrypt}}</a>
						{{/if}}
						<a class="dropdown-item" href="#" onclick="initPoll(); return false"><i id="profile-poll" class="bi bi-bar-chart jot-icons"></i>&nbsp;{{$poll}}</a>
						{{if $feature_nocomment}}
						<a class="dropdown-item" href="#" onclick="toggleNoComment(); return false;"><i id="profile-nocomment-sub" class="bi{{if $nocomment}} bi-chat{{else}} bi-chat-dots{{/if}}"></i>&nbsp;{{$nocommenttitlesub}}</a>
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
			<div id="profile-jot-submit-right" class="btn-group float-end">
				{{foreach $customsubmitright as $csr}}
				<a type="button" class="pe-3" {{$csr.buttonparams}} title="{{$csr.preview}}">
					{{$csr.buttoncontent}}
				</a>
				{{/foreach}}
				{{if $preview}}
				<a type="button" class="btn m-0" onclick="preview_post();return false;" title="{{$preview}}">
					<i class="bi bi-eye jot-icons" ></i>
				</a>
				{{/if}}
				{{if $jotnets}}
				<a type="button" id="dbtn-jotnets" class="btn m-0" data-bs-toggle="modal" data-bs-target="#jotnetsModal" type="button" title="{{$jotnets_label}}" style="{{if $lockstate == 'lock'}}display: none;{{/if}}">
					<i class="bi bi-share jot-icons"></i>
				</a>
				{{/if}}
				{{if $showacl}}
				<a type="button" id="dbtn-acl" class="btn m-0" data-bs-toggle="modal" data-bs-target="#aclModal" title="{{$permset}}" type="button" data-form_id="profile-jot-form">
					<i id="jot-perms-icon" class="bi bi-{{$lockstate}} jot-icons{{if $bang}} jot-lock-warn{{/if}}"></i>
				</a>
				{{/if}}
				<button id="dbtn-submit" class="btn btn--primary btn-sm" type="submit" tabindex="3" name="button-submit">{{$share}}</button>
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
							<h5 class="modal-title" id="expiryModalLabel">{{$jotnets_label}}</h5>
							<a type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></a>
						</div>
						<div class="modal-body">
							{{$jotnets}}
						</div>
						<div class="modal-footer">
							<a type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</a>
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

{{if $popup}}
</div>
{{/if}}

{{if $feature_expire}}
<!-- Modal for item expiry-->
<div class="modal" id="expiryModal" tabindex="-1" role="dialog" aria-labelledby="expiryModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="expiryModalLabel">{{$expires}}</h5>
				<a type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></a>
			</div>
			<div class="modal-body mb-3" style="width:90%">
				<div class="date">
					<input type="text" placeholder="yyyy-mm-dd HH:MM" name="start_text" id="expiration-date" class="" />
				</div>
				<script>
					$(function () {
						var picker = $('#expiration-date').datetimepicker({format:'Y-m-d H:i', minDate: 0 });
					});
				</script>
			</div>
			<div class="modal-footer">
				<a type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$expiryModalCANCEL}}</a>
				<a id="expiry-modal-OKButton" type="button" class="btn btn-primary">{{$expiryModalOK}}</a>
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
				<h5 class="modal-title" id="createdModalLabel">{{$future_txt}}</h5>
				<a type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></a>
			</div>
			<div class="modal-body mb-3" style="width:90%">
				<div class="date">
					<input type="text" placeholder="yyyy-mm-dd HH:MM" name="created_text" id="created-date" />
				</div>
				<script>
					$(function () {
						var picker = $('#created-date').datetimepicker({format:'Y-m-d H:i', minDate: 0 });
					});
				</script>
			</div>
			<div class="modal-footer">
				<a type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$expiryModalCANCEL}}</a>
				<a id="created-modal-OKButton" type="button" class="btn btn-primary">{{$expiryModalOK}}</a>
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
				<h5 class="modal-title" id="embedPhotoModalLabel">{{$embedPhotosModalTitle}}</h5>
				<a type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></a>
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
