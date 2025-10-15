<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header clearfix">
		<div class="float-end">
			{{if $can_post}}
			<button class="btn btn-sm btn--primary acl-form-trigger" title="{{$usage}}" onclick="openClose('photo-upload-form');" data-form_id="photos-upload-form"><i class="bi bi-plus-lg"></i>&nbsp;{{$upload.0}}</button>
			{{/if}}
		</div>
		<div class="h3 mt-0">{{$title}}</div>
	</div>
	{{$upload_form}}
	<div class="section-content-wrapper-np clearfix">
<div class="mb-3">
		<div id="photo-album-contents-{{$album_id}}" style="display: none">
			{{foreach $photos as $photo}}
				{{include file="photo_top.tpl"}}
			{{/foreach}}
			{{** make sure this element is at the bottom - we rely on that in endless scroll **}}
			<span id="page-end" class="d-block float-start w-100" style="position: absolute; bottom: 0px"></span>
		</div>
	</div>
	</div>
</div>
<div class="photos-end"></div>
<div id="page-spinner" class="spinner-wrapper">
	<div class="spinner m"></div>
</div>
<script>
$(document).ready(function() {
	loadingPage = false;
	justifyPhotos('photo-album-contents-{{$album_id}}');
});
</script>
