<div class="{{if !$no_fullscreen_btn}}generic-content-wrapper{{/if}}">
	<div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$album}}</span></div>
		<div class="buttons flush the-end">
			{{if $order}}
			<a class="btn btn-outline-secondary btn-sm" href="{{$order.1}}" title="{{$order.0}}"><i class="bi bi-arrow-down-up"></i></a>
			{{/if}}
			<div class="btn-group btn-group">
				{{if $album_edit.1}}
				<i class="bi bi-pencil btn btn-outline-secondary btn-sm" title="{{$album_edit.0}}" onclick="openClose('photo-album-edit-wrapper'); closeMenu('photo-upload-form');"></i>
				{{/if}}
				{{if $can_post}}
				<button class="btn btn-sm btn-success btn-sm" title="{{$usage}}" onclick="openClose('photo-upload-form'); {{if $album_edit.1}}closeMenu('photo-album-edit-wrapper');{{/if}}"><i class="bi bi-plus-lg"></i>&nbsp;{{$upload.0}}</button>
				{{/if}}
			</div>
		</div>
		<div class="clear"></div>
	</div>
	{{$upload_form}}
	{{$album_edit.1}}
	<div class="section-content-wrapper-np clearfix">
    <div class=" mb-3">
		<div id="photo-album-contents-{{$album_id}}" style="display: none">
			{{foreach $photos as $photo}}
				{{include file="photo_top.tpl"}}
			{{/foreach}}
			{{** make sure this element is at the bottom - we rely on that for endless scroll **}}
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
