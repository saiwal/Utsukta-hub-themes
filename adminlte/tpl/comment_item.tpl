		{{if $threaded}}
		<div class="comment-wwedit-wrapper threaded" id="comment-edit-wrapper-{{$id}}" style="display: block;">
		{{else}}
		<div class="comment-wwedit-wrapper" id="comment-edit-wrapper-{{$id}}" style="display: block;">
		{{/if}}
			<form class="comment-edit-form" style="display: block;" id="comment-edit-form-{{$id}}" action="item" method="post" onsubmit="post_comment({{$id}}); return false;">
				<input type="hidden" name="type" value="{{$type}}" />
				<input type="hidden" name="profile_uid" value="{{$profile_uid}}" />
				<input type="hidden" name="parent" value="{{$parent}}" />
				<input type="hidden" name="return" value="{{$return_path}}" />
				<input type="hidden" name="jsreload" value="{{$jsreload}}" />
				<input type="hidden" name="preview" id="comment-preview-inp-{{$id}}" value="0" />
				{{if $anoncomments && ! $observer}}
				<div id="comment-edit-anon-{{$id}}" style="display: none;" >
					{{include file="field_input.tpl" field=$anonname}}
					{{include file="field_input.tpl" field=$anonmail}}
					{{include file="field_input.tpl" field=$anonurl}}
					{{$anon_extras}}
				</div>
				{{/if}}
				<textarea id="comment-edit-text-{{$id}}" class="form-control comment-edit-text" placeholder="{{$comment}}" name="body" ondragenter="linkdropper(event);" ondragleave="linkdropexit(event);" ondragover="linkdropper(event);" ondrop="linkdrop(event);" ></textarea>
				<div id="comment-tools-{{$id}}" class="pt-2 comment-tools pb-3">
					<div id="comment-edit-bb-{{$id}}" class="btn-toolbar float-start">
						<div class="btn-group me-2">
							<button class="btn btn-sm text-body-tertiary btn-outline-secondary border-0" title="{{$edbold}}" onclick="insertbbcomment('{{$comment}}','b', {{$id}}); return false;">
								<i class="bi bi-type-bold comment-icon"></i>
							</button>
							<button class="btn btn-outline-secondary btn-sm border-0 text-body-tertiary" title="{{$editalic}}" onclick="insertbbcomment('{{$comment}}','i', {{$id}}); return false;">
								<i class="bi bi-type-italic comment-icon"></i>
							</button>
							<button class="btn btn-outline-secondary btn-sm border-0 text-body-tertiary" title="{{$eduline}}" onclick="insertbbcomment('{{$comment}}','u', {{$id}}); return false;">
								<i class="bi bi-type-underline comment-icon"></i>
							</button>
							<button class="btn btn-outline-secondary btn-sm border-0 text-body-tertiary" title="{{$edquote}}" onclick="insertbbcomment('{{$comment}}','quote', {{$id}}); return false;">
								<i class="bi bi-quote comment-icon"></i>
							</button>
							<button class="btn btn-outline-secondary btn-sm border-0 text-body-tertiary" title="{{$edcode}}" onclick="insertbbcomment('{{$comment}}','code', {{$id}}); return false;">
								<i class="bi bi-code comment-icon"></i>
							</button>
						</div>
						<div class="btn-group me-2">
							{{if $can_upload}}
							<button class="btn btn-outline-secondary btn-sm border-0 text-body-tertiary" title="{{$edatt}}" onclick="insertCommentAttach('{{$comment}}',{{$id}}); return false;">
								<i class="bi bi-paperclip comment-icon"></i>
							</button>
							{{/if}}
							<button class="btn btn-outline-secondary btn-sm border-0 text-body-tertiary" title="{{$edurl}}" onclick="insertCommentURL('{{$comment}}',{{$id}}); return false;">
								<i class="bi bi-link-45deg comment-icon"></i>
							</button>
						</div>
						{{if $feature_encrypt}}
						<div class="btn-group me-2">
							<button class="btn btn-outline-secondary btn-sm border-0 text-body-tertiary" title="{{$encrypt}}" onclick="sodium_encrypt('#comment-edit-text-' + '{{$id}}'); return false;">
								<i class="bi bi-key comment-icon"></i>
							</button>
						</div>
						{{/if}}
						{{$comment_buttons}}
					</div>
					<div class="btn-group float-end" id="comment-edit-submit-wrapper-{{$id}}">
						{{if $preview}}
						<button id="comment-edit-presubmit-{{$id}}" class="btn btn-outline-secondary btn-sm text-body-tertiary" onclick="preview_comment({{$id}}); return false;" title="{{$preview}}">
							<i class="bi bi-eye comment-icon" ></i>
						</button>
						{{/if}}
						<button id="comment-edit-submit-{{$id}}" class="btn btn-primary btn-sm text-body-tertiary" type="submit" name="button-submit" onclick="post_comment({{$id}}); return false;">{{$submit}}</button>
					</div>
				</div>
				<div class="clear"></div>
			</form>
		</div>
		<div id="comment-edit-preview-{{$id}}" class="comment-edit-preview mt-4"></div>
