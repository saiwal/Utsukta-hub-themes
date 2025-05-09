<form>
<div class="modal" id="aclModal">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">
					<i id="dialog-perms-icon" class="bi"></i> {{$aclModalTitle}}
					{{if $helpUrl}}
					<a target="hubzilla-help" href="{{$helpUrl}}" class="contextual-help-tool" title="Help and documentation"><i class="bi bi-question-lg"></i></a>
					{{/if}}
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
			</div>
			<div class="section-content-wrapper modal-body">
				{{if $aclModalDesc}}
				<div id="acl-dialog-description" class="section-content-info-wrapper mb-2">{{$aclModalDesc}}</div>
				{{/if}}
				<label for="acl-select">{{if $aclModalDesc}}<i class="bi bi-send"></i> {{/if}}{{$select_label}}</label>
				<select id="acl-select" name="optionsRadios" class="form-control mb-3">
					<option id="acl-showall" value="public" {{$public_selected}}>{{$showall}}</option>
					<option id="acl-onlyme" value="onlyme" {{$justme_selected}}>{{$onlyme}}</option>
					{{$groups}}
					<optgroup label = "{{$custom_label}}">;
						<option id="acl-custom" value="custom" {{$custom_selected}}>{{$custom}}</option>
					</optgroup>;
				</select>

				{{if $showallOrigin}}
				<div id="acl-info" class="mb-3">
					<i class="bi bi-info-circle"></i>&nbsp;{{$showallOrigin}}
				</div>
				{{/if}}

				<div id="acl-wrapper">
					<div id="acl-list">
						<input class="form-control" type="text" id="acl-search" placeholder="{{$search}}" title="{{$search}}">
						<small class="text-muted">{{$showlimitedDesc}}</small>
						<div id="acl-list-content"></div>
					</div>
				</div>

				<div class="list-group-item acl-list-item" rel="acl-template" style="display:none">
					<div class="acl-item-header clearfix">
						<img class="menu-img-3" data-src="{0}" loading="lazy" />
						<span class="font-weight-bold contactname">{1}</span>
						<span class="dropdown-sub-text">{6}</span>
					</div>
					<button class="acl-button-hide btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> {{$hide}}</button>
					<button class="acl-button-show btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i> {{$show}}</button>
				</div>
			</div>
			<div class="modal-footer clear">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$aclModalDismiss}}</button>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</form>
<script>
	// compatibility issue with bootstrap v4
	//$('[data-bs-toggle="popover"]').popover(); // Init the popover, if present

	if(typeof acl=="undefined"){
		acl = new ACL(
			baseurl+"/acl"
		);
	}
</script>
