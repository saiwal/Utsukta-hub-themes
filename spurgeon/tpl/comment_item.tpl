		<div class="comment-wwedit-wrapper{{if $threaded}} threaded{{/if}}" id="comment-edit-wrapper-{{$id}}">
			<form class="comment-edit-form w-100" id="comment-edit-form-{{$id}}" action="item" method="post" onsubmit="post_comment({{$id}}); return false;">
				<input type="hidden" name="type" value="{{$type}}" />
				<input type="hidden" name="profile_uid" value="{{$profile_uid}}" />
				<input type="hidden" name="parent" value="{{$parent}}" />
				<input type="hidden" name="return" value="{{$return_path}}" />
				<input type="hidden" name="jsreload" value="{{$jsreload}}" />
				<input type="hidden" name="preview" id="comment-preview-inp-{{$id}}" value="0" />
				{{if $anoncomments && !$observer}}
				<div id="comment-edit-anon-{{$id}}" style="display: none;" >
					{{include file="field_input.tpl" field=$anonname}}
					{{include file="field_input.tpl" field=$anonmail}}
					{{include file="field_input.tpl" field=$anonurl}}
					{{$anon_extras}}
				</div>
				{{/if}}
				<textarea id="comment-edit-text-{{$id}}" class="comment-edit-text form-control" placeholder="{{$comment}}" name="body" ondragenter="linkdropper(event);" ondragleave="linkdropexit(event);" ondragover="linkdropper(event);" ondrop="linkdrop(event);" ></textarea>
				<div id="comment-tools-{{$id}}" class="pt-2 comment-tools">
					<div id="comment-edit-bb-{{$id}}" class="btn-toolbar float-start">
						<div class="btn-group me-2">
							<button type="button" class="btn btn-outline-secondary btn-sm border-0" title="{{$edbold}}" onclick="insertbbcomment('{{$comment}}','b', {{$id}});">
								<i class="bi bi-type-bold comment-icon"></i>
							</button>
							<button type="button" class="btn btn-outline-secondary btn-sm border-0" title="{{$editalic}}" onclick="insertbbcomment('{{$comment}}','i', {{$id}});">
								<i class="bi bi-type-italic comment-icon"></i>
							</button>
							<button type="button" class="btn btn-outline-secondary btn-sm border-0" title="{{$eduline}}" onclick="insertbbcomment('{{$comment}}','u', {{$id}});">
								<i class="bi bi-type-underline comment-icon"></i>
							</button>
							<button type="button" class="btn btn-outline-secondary btn-sm border-0" title="{{$edquote}}" onclick="insertbbcomment('{{$comment}}','quote', {{$id}});">
								<i class="bi bi-quote comment-icon"></i>
							</button>
							<button type="button" class="btn btn-outline-secondary btn-sm border-0" title="{{$edcode}}" onclick="insertbbcomment('{{$comment}}','code', {{$id}});">
								<i class="bi bi-code comment-icon"></i>
							</button>
							<button type="button" class="btn btn-outline-secondary btn-sm border-0" title="{{$edhighlighter}}" onclick="insertbbcomment('{{$comment}}','mark', {{$id}});">
								<i class="bi bi-highlighter comment-icon"></i>
							</button>
						</div>
						<div class="btn-group me-2">
							{{if $can_upload}}
							<button type="button" class="btn btn-outline-secondary btn-sm border-0" title="{{$edatt}}" onclick="insertCommentAttach('{{$comment}}', {{$id}});">
								<i class="bi bi-paperclip comment-icon"></i>
							</button>
							<button type="button" title="{{$edimg}}" class="btn btn-outline-secondary btn-sm border-0" onclick="insertCommentEmbed('{{$comment}}', {{$id}});">
								<i class="bi bi-file-image comment-icon"></i>
							</button>
							{{/if}}
							<button type="button" class="btn btn-outline-secondary btn-sm border-0" title="{{$edurl}}" onclick="insertCommentURL('{{$comment}}',{{$id}});">
								<i class="bi bi-link-45deg comment-icon"></i>
							</button>
						</div>
						{{if $feature_encrypt}}
						<div class="btn-group me-2">
							<button type="button" class="btn btn-outline-secondary btn-sm border-0" title="{{$encrypt}}" onclick="sodium_encrypt('#comment-edit-text-' + '{{$id}}');">
								<i class="bi bi-key comment-icon"></i>
							</button>
						</div>
						{{/if}}
						{{$comment_buttons}}
					</div>
					<div class="btn-group float-end" id="comment-edit-submit-wrapper-{{$id}}">
						{{if $preview}}
						<button type="button" id="comment-edit-presubmit-{{$id}}" class="btn btn-outline-secondary btn-sm" onclick="preview_comment({{$id}});" title="{{$preview}}">
							<i class="bi bi-eye comment-icon" ></i>
						</button>
						{{/if}}
						<button id="comment-edit-submit-{{$id}}" class="btn btn-primary btn-sm" type="submit" name="button-submit" onclick="post_comment({{$id}}); return false;">{{$submit}}</button>
					</div>
				</div>
				<div class="clear"></div>
			</form>
			<div id="comment-edit-preview-{{$id}}" class="comment-edit-preview w-100"></div>
		</div>
