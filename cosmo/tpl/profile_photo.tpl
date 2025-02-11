<input id="invisible-photos-file-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50px;left:-50px;width:0;height:0;">

<div id="profile-photo-content" class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}: {{$profile.name}}</h3>
	</div>
	<div class="section-content-wrapper">

		<form enctype="multipart/form-data" id="profile-photo-form" action="profile_photo" method="post">
			<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

			<div id="profile-photo-upload-wrapper">
				{{if $info}}
					<div class="section-content-warning-wrapper">{{$info}}</div>
				{{/if}}
				{{if $importfile}}
					<input type="hidden" name="importfile" value="{{$importfile}}">
				{{/if}}
				<div class="clear mb-3"></div>

				<input type="hidden" name="profile" value="{{$profile_id}}"/>

				<div id="profile-photo-upload-spinner" class="spinner-wrapper">
					<div class="spinner m"></div>
				</div>
				<div id="profile-photo-submit-wrapper">
					<button type="submit" class="btn btn-primary float-end" name="submit" id="profile-photo-submit">
						{{$submit}}
					</button>
					<button id="embed-photo-wrapper" type="button"
							class="btn btn-default btn-outline-success float-end me-1" title="{{$embedPhotos}}"
							onclick="initializeEmbedPhotoDialog();">
						<i id="embed-photo" class="bi bi-file-image"></i> {{$select}}
					</button>
					<button type="submit" class="btn btn-outline-danger" name="remove" id="profile-photo-remove">
						{{$remove}}
					</button>
				</div>
			</div>

		</form>
	</div>
</div>

<div class="modal" id="embedPhotoModal" tabindex="-1" role="dialog" aria-labelledby="embedPhotoLabel"
	 aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="embedPhotoModalLabel">{{$embedPhotosModalTitle}}</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
			</div>
			<div class="modal-body" id="embedPhotoModalBody">
				<div id="embedPhotoModalBodyAlbumListDialog" class="d-none">
					<div id="embedPhotoModalBodyAlbumList"></div>
				</div>
				<div id="embedPhotoModalBodyAlbumDialog" class="d-none"></div>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<script src="vendor/blueimp/jquery-file-upload/js/vendor/jquery.ui.widget.js"></script>
<script src="vendor/blueimp/jquery-file-upload/js/jquery.iframe-transport.js"></script>
<script src="vendor/blueimp/jquery-file-upload/js/jquery.fileupload.js"></script>
<script>
	var initializeEmbedPhotoDialog = function () {
		$('.embed-photo-selected-photo').each(function (index) {
			$(this).removeClass('embed-photo-selected-photo');
		});
		getPhotoAlbumList();
		$('#embedPhotoModalBodyAlbumDialog').off('click');
		$('#embedPhotoModal').modal('show');
	};

	var choosePhotoFromAlbum = function (album) {
		$.post("embedphotos/album", {name: album},
			function (data) {
				if (data['status']) {
					$('#embedPhotoModalLabel').html("{{$modalchooseimages}}");
					$('#embedPhotoModalBodyAlbumDialog').html('\
							<div><div class="nav nav-pills flex-column">\n\
								<li class="nav-item"><a class="nav-link" href="#" onclick="initializeEmbedPhotoDialog();return false;">\n\
									<i class="bi fa-chevron-left"></i>&nbsp\n\
									{{$modaldiffalbum}}\n\
									</a>\n\
								</li>\n\
							</div><br></div>')
					$('#embedPhotoModalBodyAlbumDialog').append(data['content']);
					$('#embedPhotoModalBodyAlbumDialog').click(function (evt) {
						evt.preventDefault();
						var image = document.getElementById(evt.target.id);
						if (typeof ($(image).parent()[0]) !== 'undefined') {
							var imageparent = document.getElementById($(image).parent()[0].id);
							$(imageparent).toggleClass('embed-photo-selected-photo');
							var href = $(imageparent).attr('href');
							$.post("embedphotos/photolink", {href: href},
								function (ddata) {
									if (ddata['status']) {
										window.location.href = 'profile_photo/use/' + ddata['resource_id'] + '?f=&profile={{$profile_id}}';
									} else {
										window.console.log("{{$modalerrorlink}}" + ':' + ddata['errormsg']);
									}
									return false;
								},
								'json');
							$('#embedPhotoModalBodyAlbumDialog').html('');
							$('#embedPhotoModalBodyAlbumDialog').off('click');
							$('#embedPhotoModal').modal('hide');
						}
					});

					$('#embedPhotoModalBodyAlbumListDialog').addClass('d-none');
					$('#embedPhotoModalBodyAlbumDialog').removeClass('d-none');
				} else {
					window.console.log("{{$modalerroralbum}} " + JSON.stringify(album) + ':' + data['errormsg']);
				}
				return false;
			},
			'json');
	};

	var getPhotoAlbumList = function () {
		$.post("embedphotos/albumlist", {},
			function (data) {
				if (data['status']) {
					var albums = data['albumlist']; //JSON.parse(data['albumlist']);
					$('#embedPhotoModalLabel').html("{{$modalchoosealbum}}");
					$('#embedPhotoModalBodyAlbumList').html('<ul class="nav nav-pills flex-column"></ul>');
					for (var i = 0; i < albums.length; i++) {
						var albumName = albums[i].text;
						var jsAlbumName = albums[i].jstext;
						var albumLink = '<li class="nav-item">';
						albumLink += '<a class="nav-link" href="#" onclick="choosePhotoFromAlbum(\'' + jsAlbumName + '\'); return false;">' + albumName + '</a>';
						albumLink += '</li>';
						$('#embedPhotoModalBodyAlbumList').find('ul').append(albumLink);
					}
					$('#embedPhotoModalBodyAlbumDialog').addClass('d-none');
					$('#embedPhotoModalBodyAlbumListDialog').removeClass('d-none');
				} else {
					window.console.log("{{$modalerrorlist}}" + ':' + data['errormsg']);
				}
				return false;
			},
			'json');
	};


	$('#invisible-photos-file-upload').fileupload({
		url: 'profile_photo',
		dataType: 'json',
		// dropZone: filedrag,
		maxChunkSize: 2 * 1024 * 1024,

		add: function (e, data) {
			data.formData = $('#profile-photo-form').serializeArray();
			data.submit();
			$('#profile-photo-upload-spinner').show();
		},

		done: function (e, data) {
			$('#profile-photo-upload-spinner').hide();
			window.location.href = 'profile_photo/use/' + data.result.message + '?profile={{$profile_id}}';
		}

	});

	$('#profile-photo-submit').click(function (event) {
		event.preventDefault();
		$('#invisible-photos-file-upload').trigger('click');
		return false;
	});
</script>
