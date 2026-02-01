<script src="vendor/blueimp/jquery-file-upload/js/vendor/jquery.ui.widget.js"></script>
<script src="vendor/blueimp/jquery-file-upload/js/jquery.iframe-transport.js"></script>
<script src="vendor/blueimp/jquery-file-upload/js/jquery.fileupload.js"></script>

<script>

	var editor = false;
	var plaintext = '{{$editselect}}';
	var pretext = '{{$pretext}}';

	var activeCommentID = 0;
	var activeCommentText = '';

	var isModalAction = false;

	var postSaveTimer = null;

	function initEditor(cb){
		if(editor == false){
			$("#profile-jot-text-loading").show();
			$("#profile-jot-tools").removeClass('d-none');
			{{$geotag}}
			if(plaintext == 'none') {
				$("#profile-jot-text-loading").hide();
				$(".jothidden").show();
				$("#profile-jot-text").addClass('jot-expanded');
				{{if $bbco_autocomplete}}
				$("#profile-jot-text").bbco_autocomplete('{{$bbco_autocomplete}}'); // autocomplete bbcode
				{{/if}}
				{{if $editor_autocomplete}}
				if(typeof channelId === 'undefined')
					$("#profile-jot-text").editor_autocomplete(baseurl+"/acl");
				else
					$("#profile-jot-text").editor_autocomplete(baseurl+"/acl",[channelId]); // Also gives suggestions from current channel's connections
				{{/if}}
				editor = true;
				if (typeof cb!="undefined") cb();
				if(pretext.length)
					addeditortext(pretext);
				return;
			}
				editor = true;
		} else {
			if (typeof cb!="undefined") cb();
		}
	}

	function enableOnUser(){
		if(editor)
			return;

		initEditor();
	}

	$(document).ready(function() {

		$("#profile-jot-text").focus(enableOnUser);
		$("#profile-jot-text").click(enableOnUser);

		$('#id_mimetype').on('load', jotSetMime);
		$('#id_mimetype').on('change', jotSetMime);

		$('#jot-add-option').on('click', jotAddOption);
		$(document).on('click', '.poll-option-close', jotRemoveOption);

		$('#profile-jot-form').keydown(function(e) {
			if (e.ctrlKey && e.keyCode === 13) {
				$(this).trigger('submit');
			}
		});

		$('#invisible-wall-file-upload').fileupload({
			url: 'wall_attach/{{$nickname}}',
			dataType: 'json',
			dropZone: $('#profile-jot-text'),
			maxChunkSize: 4 * 1024 * 1024,
			add: function(e,data) {
				$('#profile-rotator').show();
				data.submit();
			},
			done: function(e,data) {
				addeditortext(data.result.message);
				$('#jot-media').val($('#jot-media').val() + data.result.message);
			},
			stop: function(e,data) {
				preview_post();
				$('#profile-rotator').hide();
			},
		});

		$('#wall-file-upload').click(function(event) { event.preventDefault(); $('#invisible-wall-file-upload').trigger('click'); return false;});
		$('#wall-file-upload-sub').click(function(event) { event.preventDefault(); $('#invisible-wall-file-upload').trigger('click'); return false;});

		// call initialization file
		if (window.File && window.FileList && window.FileReader) {
			DragDropUploadInit();
		}

		$('#invisible-comment-upload').fileupload({
			url: 'wall_attach/{{$nickname}}',
			dataType: 'json',
			dropZone: $(),
			maxChunkSize: 4 * 1024 * 1024,
			add: function(e,data) {

				var tmpStr = $("#comment-edit-text-" + activeCommentID).val();
				if(tmpStr == activeCommentText) {
					tmpStr = "";
					$("#comment-edit-text-" + activeCommentID).addClass("comment-edit-text-full");
					$("#comment-edit-text-" + activeCommentID).removeClass("comment-edit-text-empty");
					openMenu("comment-tools-" + activeCommentID);
					$("#comment-edit-text-" + activeCommentID).val(tmpStr);
				}
				data.submit();
			},

			done: function(e,data) {
				textarea = document.getElementById("comment-edit-text-" + activeCommentID);
				textarea.value = textarea.value + data.result.message;
			},
			stop: function(e,data) {
				$('body').css('cursor', 'auto');
				preview_comment(activeCommentID);
				activeCommentID = 0;
			},
		});

	});

	function jotSetMime() {
		var mtype = $('#id_mimetype').val();
		if(mtype == 'text/bbcode')
			$('#profile-jot-submit-left').show();
		else
			$('#profile-jot-submit-left').hide();
	}

	function deleteCheckedItems() {
		var checkedstr = '';

		$('.item-select').each( function() {
			if($(this).is(':checked')) {
				if(checkedstr.length != 0) {
					checkedstr = checkedstr + ',' + $(this).val();
				}
				else {
					checkedstr = $(this).val();
				}
			}
		});
		$.post('item', { dropitems: checkedstr }, function(data) {
			window.location.reload();
		});
	}

	function jotGetLink() {
			textarea = document.getElementById('profile-jot-text');
			if (textarea.selectionStart || textarea.selectionStart == "0") {
					var start = textarea.selectionStart;
					var end = textarea.selectionEnd;
					if (end > start) {
						reply = prompt("{{$linkurl}}");
						if(reply && reply.length) {
							textarea.value = textarea.value.substring(0, start) + "[url=" + reply + "]" + textarea.value.substring(start, end) + "[/url]" + textarea.value.substring(end, textarea.value.length);
						}
					} else {
						reply = prompt("{{$linkurl}}");
						if(reply && reply.length) {
							reply = bin2hex(reply);
							$('#profile-rotator').show();
							$.get('{{$baseurl}}/linkinfo?f=&binurl=' + reply, function(data) {
									addeditortext(data);
									preview_post();
									$('#profile-rotator').hide();
							});
						}
					}
			}
	}

	function jotGetLocation() {
		reply = prompt("{{$whereareu}}", $('#jot-location').val());
		if(reply && reply.length) {
			$('#jot-location').val(reply);
		}
	}

	function jotGetExpiry() {
		$('#expiryModal').modal('show');
		$('#expiry-modal-OKButton').on('click', function() {
			reply=$('#expiration-date').val();
			if(reply && reply.length) {
				$('#jot-expire').val(reply);
				$('#expiryModal').modal('hide');
			}
		})
	}

	function jotGetPubDate() {
		$('#createdModal').modal('show');
		$('#created-modal-OKButton').on('click', function() {
			reply=$('#created-date').val();
			if(reply && reply.length) {
				$('#jot-delayed').val(1);
				$('#jot-created').val(reply);
				$('#createdModal').modal('hide');
			}
		})
	}

	function jotShare(id,post_type) {
		$('#like-rotator-' + id).show();
		$.get('{{$baseurl}}/share/' + id, function(data) {
			$('#like-rotator-' + id).hide();
			updateInit();
			close_modal();
		});
	}

	function jotEmbed(id,post_type) {
		if(post_type == 6) {
			window.location.href = 'rpost?f=&post_id='+id;
		}
		else {

			if ($('#jot-popup').length != 0) $('#jot-popup').show();

			$('#like-rotator-' + id).show();
			$.get('{{$baseurl}}/embed/' + id, function(data) {
				if (!editor) $("#profile-jot-text").val("");
				initEditor(function(){
					addeditortext(data);
					$('#like-rotator-' + id).hide();
					$(window).scrollTop(0);
				});
			});
		}
	}

	function linkdropper(event) {
		var linkFound = ((event.dataTransfer.types.indexOf("text/uri-list") > -1) ? true : false);
		if(linkFound) {
			event.preventDefault();
			var editwin = '#' + event.target.id;
			var commentwin = false;
			if(editwin) {
				commentwin = ((editwin.indexOf('comment') >= 0) ? true : false);
				if(commentwin) {
					var commentid = editwin.substring(editwin.lastIndexOf('-') + 1);
					$('#comment-edit-text-' + commentid).addClass('hover');
				}
			}
		}
	}

	function linkdropexit(event) {
		var editwin = '#' + event.target.id;
		var commentwin = false;
		if(editwin) {
			commentwin = ((editwin.indexOf('comment') >= 0) ? true : false);
			if(commentwin) {
				var commentid = editwin.substring(editwin.lastIndexOf('-') + 1);
				$('#comment-edit-text-' + commentid).removeClass('hover');
			}
		}
	}

	function linkdrop(event) {
		var reply = event.dataTransfer.getData("text/uri-list");
		if(reply) {
			event.preventDefault();
			var editwin = '#' + event.target.id;
			var commentwin = false;
			if(editwin) {
				commentwin = ((editwin.indexOf('comment') >= 0) ? true : false);
				if(commentwin) {
					var commentid = editwin.substring(editwin.lastIndexOf('-') + 1);
					$("#comment-edit-text-" + commentid).addClass("expanded");

				}
			}
		}

		if(reply && reply.length) {
			reply = bin2hex(reply);
			$('#profile-rotator').show();
			$.get('{{$baseurl}}/linkinfo?f=&binurl=' + reply, function(data) {
				if(commentwin) {
					$(editwin).val( $(editwin).val() + data );
					$('#profile-rotator').hide();
				}
				else {
					if (!editor) $("#profile-jot-text").val("");
					initEditor(function(){
					addeditortext(data);
					$('#profile-rotator').hide();
					});
				}
			});
		}
	}

	function itemTag(id) {
		reply = prompt("{{$term}}");
		if(reply && reply.length) {
			reply = reply.replace('#','');
			if(reply.length) {
				commentBusy = true;
				$('body').css('cursor', 'wait');
				$.get('{{$baseurl}}/tagger/' + id + '?term=' + reply, updateInit);
				liking = 1;
			}
		}
	}

	function itemCancel() {
		$("#jot-title").val('');
		$("#jot-summary").val('');
		$("#profile-jot-text").val('');
		$(".jot-poll-option input").val('');
		$("#jot-category").tagsinput('removeAll');

		postSaveChanges('clean');

		{{if $popup}}
		$("#profile-jot-text").focus();
		{{else if $reset}}
		$(".jothidden").hide();
		$("#profile-jot-text").removeClass('jot-expanded');
		$("#profile-jot-tools").addClass('d-none');
		$("#jot-poll-wrap").addClass('d-none');
		$("#jot-preview-content").html('').hide();
		editor = false;
		{{else}}
		window.history.back();
		{{/if}}
	}

	function itemFiler(id) {
		if($('#item-filer-dialog').length)
			$('#item-filer-dialog').remove();

		$.get('filer/', function(data){
			$('body').append(data);
			$('#item-filer-dialog').modal('show');
			$("#filer_save").click(function(e){
				e.preventDefault();
				reply = $("#id_term").val();
				if(reply && reply.length) {
					commentBusy = true;
					$('body').css('cursor', 'wait');
					$.get('{{$baseurl}}/filer/' + id + '?term=' + reply, updateInit);
					liking = 1;
					$('#item-filer-dialog').modal('hide');
				}
				return false;
			});
		});

	}

	function itemFilerRm(id, term) {
		commentBusy = true;
		$('body').css('cursor', 'wait');
		$.get('{{$baseurl}}/filerm/' + id + '?f=&term=' + term, updateInit);
		liking = 1;
	}

	function itemBookmark(id) {
		$.get('{{$baseurl}}/bookmarks?f=&item=' + id);
		if(timer) clearTimeout(timer);
		timer = setTimeout(updateInit,1000);
	}

	function itemAddToCal(id) {
		$.get('{{$baseurl}}/channel_calendar/add/' + id);
		if(timer) clearTimeout(timer);
		timer = setTimeout(updateInit,1000);
	}

	function toggleNoComment() {
		if($('#jot-nocomment').val() > 0) {
			$('#jot-nocomment').val(0);
			$('#profile-nocomment, #profile-nocomment-sub').removeClass('bi-chat').addClass('bi-chat-dots');
			$('#profile-nocomment-wrapper').attr('title', '{{$nocomment_enabled|escape:'javascript'}}');
		}
		else {
			$('#jot-nocomment').val(1);
			$('#profile-nocomment, #profile-nocomment-sub').removeClass('bi-chat-dots').addClass('bi-chat');
			$('#profile-nocomment-wrapper').attr('title', '{{$nocomment_disabled|escape:'javascript'}}');
		}
	}

	function jotReact(id,icon) {
		if(id && icon) {
			$('#like-rotator-' + id).show();
			$.get('{{$baseurl}}/react?f=&postid=' + id + '&emoji=' + icon);
			if(timer) clearTimeout(timer);
			timer = setTimeout(updateInit,1000);
		}
	}

	function jotClearLocation() {
		$('#jot-coord').val('');
		$('#profile-nolocation-wrapper').attr('disabled', true);
	}


	const initializeEmbedPhotoDialog = () => {
		// Remove the 'embed-photo-selected-photo' class from all selected photos
		const selectedPhotos = document.querySelectorAll('.embed-photo-selected-photo');
		selectedPhotos.forEach(photo => {
			photo.classList.remove('embed-photo-selected-photo');
		});

		// Fetch the photo album list
		getPhotoAlbumList();

		if (activeCommentID) {
			const modalEl = document.getElementById('reactions');
			isModalAction = modalEl.classList.contains('show');
		}

		if (isModalAction) {
			// Remove any existing click event listeners on the modal body
			const modalBodyAlbumDialog = document.getElementById('reactions_extra_middle');
			modalBodyAlbumDialog.replaceWith(modalBodyAlbumDialog.cloneNode(true)); // This effectively removes all event listeners

			const modalEl = document.getElementById('reactions');

			// Reset activeCommentID when the modal is closed
			modalEl.addEventListener('hide.bs.modal', event => {
				activeCommentID = 0;
				isModalAction = false;
				document.getElementById('reactions_extra_middle').innerHTML = '';
				document.getElementById('reactions_extra_top').innerHTML = '';
			});
		}
		else {
			// Remove any existing click event listeners on the modal body
			const modalBodyAlbumDialog = document.getElementById('embedPhotoModalBodyAlbumDialog');
			modalBodyAlbumDialog.replaceWith(modalBodyAlbumDialog.cloneNode(true)); // This effectively removes all event listeners

			// Show the modal
			const modalEl = document.getElementById('embedPhotoModal');
			const modal = new bootstrap.Modal(modalEl);
			modal.show();

			// Reset activeCommentID when the modal is closed
			modalEl.addEventListener('hide.bs.modal', event => {
				activeCommentID = 0;
			});
		}
	};

	const choosePhotoFromAlbum = (album) => {

		const params = new URLSearchParams();
		params.append('name', album);

		fetch('embedphotos/album', {
			method: 'POST',
			body: params
		})
		.then(response => response.json())
		.then(data => {
			if (data.status) {

				const modalLabel = isModalAction ? document.getElementById('reactions_extra_top') : document.getElementById('embedPhotoModalLabel');
				const modalBody = isModalAction ? document.getElementById('reactions_extra_middle') : document.getElementById('embedPhotoModalBodyAlbumDialog');

				modalLabel.innerHTML = '<h3>{{$modalchooseimages}}</h3>';
				modalBody.innerHTML = '<div><div class="nav nav-pills flex-column"><li class="nav-item"><a class="nav-link" href="#" onclick="initializeEmbedPhotoDialog(); return false;"><i class="bi bi-chevron-left"></i>&nbsp;{{$modaldiffalbum}}</a></li></div><br></div>';
				modalBody.innerHTML += data.content;

				// Make sure the loaded script is executed
				const scripts = modalBody.querySelectorAll('script');

				scripts.forEach(script => {
					const scriptContent = script.textContent || script.innerText;
					eval(scriptContent);  // Execute the script
				});

				modalBody.addEventListener('click', (evt) => {
					evt.preventDefault();
					const image = document.getElementById(evt.target.id);
					if (image && image.parentElement) {
						const imageParent = image.parentElement;
						imageParent.classList.toggle('embed-photo-selected-photo');
						const href = imageParent.getAttribute('href');

						const params = new URLSearchParams();
						params.append('href', href);

						fetch('embedphotos/photolink', {
							method: 'POST',
							body: params
						})
						.then(response => response.json())
						.then(ddata => {
							if (ddata.status) {
								addActiveEditorText(ddata.photolink);
							} else {
								console.error("{{$modalerrorlink}}: " + ddata.errormsg);
							}
						});
					}
				});

				document.getElementById('embedPhotoModalBodyAlbumListDialog').classList.add('d-none');
				modalBody.classList.remove('d-none');
			} else {
				console.error("{{$modalerroralbum}} " + JSON.stringify(album) + ': ' + data.errormsg);
			}
		})
		.catch(error => {
			console.error("Error fetching album:", error);
		});
	};

	const getPhotoAlbumList = () => {
		fetch('embedphotos/albumlist', {
			method: 'POST',
		})
		.then(response => response.json())
		.then(data => {
			if (data.status) {
				const albums = data.albumlist;
				const modalLabel = isModalAction ? document.getElementById('reactions_extra_top') : document.getElementById('embedPhotoModalLabel');
				const modalBodyList = isModalAction ? document.getElementById('reactions_extra_middle') : document.getElementById('embedPhotoModalBodyAlbumList');

				modalLabel.innerHTML = '<h3>{{$modalchoosealbum}}</h3>';
				modalBodyList.innerHTML = '<ul class="nav nav-pills flex-column"></ul>';

				albums.forEach(album => {
					const albumName = album.text;
					const jsAlbumName = album.jstext;
					const albumLink = '<li class="nav-item"><a class="nav-link" href="#" onclick="choosePhotoFromAlbum(\'' + jsAlbumName + '\'); return false;">' + albumName + '</a></li>';
					modalBodyList.querySelector('ul').innerHTML += albumLink;
				});

				document.getElementById('embedPhotoModalBodyAlbumDialog').classList.add('d-none');
				document.getElementById('embedPhotoModalBodyAlbumListDialog').classList.remove('d-none');
			} else {
				console.error("{{$modalerrorlist}}: " + data.errormsg);
			}
		})
		.catch(error => {
			console.error("Error fetching album list:", error);
		});
	};

	function addeditortext(data) {
		if(plaintext == 'none') {
			var currentText = $("#profile-jot-text").val();
			$("#profile-jot-text").val(currentText + data);
		}
	}

	// Add text to active comment region if set, otherwise add to main editor
	function addActiveEditorText(data) {
		if (activeCommentID) {
			const textarea = document.getElementById('comment-edit-text-' + activeCommentID);

			if (textarea) {
				let currentText = textarea.value;

				// Clear the textarea if it matches the active comment text
				if (currentText === activeCommentText) {
					currentText = '';
				}

				textarea.classList.add('expanded');
				openMenu('comment-tools-' + activeCommentID);
				textarea.value = currentText + data;
				textarea.focus();
				textarea.click();
				if (!isModalAction) {
					preview_comment(activeCommentID);
				}
			}
		} else {
			addeditortext(data);
			preview_post();
		}
	}


    // initialize drag-drop
    function DragDropUploadInit() {

      var filedrag = $("#profile-jot-text");

	  // file drop
        filedrag.on("dragover", DragDropUploadFileHover);
        filedrag.on("dragleave", DragDropUploadFileHover);
        filedrag.on("drop", DragDropUploadFileSelectHandler);

    }

	// file drag hover
	function DragDropUploadFileHover(e) {
		if(e.type == 'dragover')
			$(e.target).addClass('hover');
		else
			$(e.target).removeClass('hover');
	}

    // file selection
    function DragDropUploadFileSelectHandler(e) {

      // cancel event and hover styling
      DragDropUploadFileHover(e);
      // open editor if it isn't yet initialised
	  if (!editor) {
			enableOnUser();
	  }
	  linkdrop(e);

    }

	function initPoll() {
		$('#jot-poll-wrap').toggleClass('d-none');
	}

	function jotAddOption() {
		var option = '<div class="jot-poll-option mb-3"><input class="full-width" name="poll_answers[]" type="text" value="" placeholder="Option"><div class="poll-option-close input-group-text"><i class="bi bi-x-lg"></i></div></div>';
		$('#jot-poll-options').append(option);
	}

	function jotRemoveOption(e) {
		$(this).closest('.jot-poll-option').remove();
	}


	$( document ).on( "click", ".wall-item-delete-link,.page-delete-link,.layout-delete-link,.block-delete-link", function(e) {
		var link = $(this).attr("href"); // "get" the intended link in a var

		if (typeof(eval($.fn.modal)) === 'function'){
			e.preventDefault();
			bootbox.confirm("<h4>{{$confirmdelete}}</h4>",function(result) {
				if (result) {
					document.location.href = link;
				}
			});
		} else {
			return confirm("{{$confirmdelete}}");
		}
	});

	function postSaveChanges(action) {
		if({{$auto_save_draft}}) {

			var doctype = $('#jot-webpage').val();
			var postid = '-' + doctype + '-' + $('#jot-postid').val();

			if(action != 'clean') {
				localStorage.setItem("post_title" + postid, $("#jot-title").val());
				localStorage.setItem("post_summary" + postid, $("#jot-summary").val());
				localStorage.setItem("post_body" + postid, $("#profile-jot-text").val());
				if($("#jot-category").length)
					localStorage.setItem("post_category" + postid, $("#jot-category").val());
			}

			if(action == 'start') {
				postSaveTimer = setTimeout(function () {
					postSaveChanges('start');
				},10000);
			}

			if(action == 'stop') {
				clearTimeout(postSaveTimer);
				postSaveTimer = null;
			}

			if(action == 'clean') {
				clearTimeout(postSaveTimer);
				postSaveTimer = null;
				localStorage.removeItem("post_title" + postid);
				localStorage.removeItem("post_summary" + postid);
				localStorage.removeItem("post_body" + postid);
				localStorage.removeItem("post_category" + postid);
			}
		}

	}

	$(document).ready(function() {
		var cleaned = false;

		if({{$auto_save_draft}}) {
			var doctype = $('#jot-webpage').val();
			var postid = '-' + doctype + '-' + $('#jot-postid').val();
			var postTitle = localStorage.getItem("post_title" + postid);
			var postSummary = localStorage.getItem("post_summary" + postid);
			var postBody = localStorage.getItem("post_body" + postid);
			var postCategory = (($("#jot-category").length) ? localStorage.getItem("post_category" + postid) : '');
			var openEditor = false;

			if(postTitle) {
				$('#jot-title').val(postTitle);
				openEditor = true;
			}
			if(postSummary) {
				$('#jot-summary').val(postSummary);
				openEditor = true;
			}
			if(postBody) {
				$('#profile-jot-text').val(postBody);
				openEditor = true;
			}
			if(postCategory) {
				var categories = postCategory.split(',');
				categories.forEach(function(cat) {
					$('#jot-category').tagsinput('add', cat);
				});
				openEditor = true;
			}
			if(openEditor) {
				enableOnUser();
			}
		} else {
			postSaveChanges('clean');
		}

		$(document).on('submit', '#profile-jot-form', function() {
			postSaveChanges('clean');
			cleaned = true;
		});

		$(document).on('focusout',"#profile-jot-wrapper",function(e){
			if(! cleaned)
				postSaveChanges('stop');
		});

		$(document).on('focusin',"#profile-jot-wrapper",function(e){
			postSaveTimer = setTimeout(function () {
				postSaveChanges('start');
			},10000);
		});


	});
</script>
