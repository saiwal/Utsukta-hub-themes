<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$header}}</h3>
	</div>
	<div class="section-content-wrapper-np">
		<form id="abook-edit-form" action="defperms/{{$contact_id}}" method="post" >

		<input type="hidden" name="contact_id" value="{{$contact_id}}">
		<input type="hidden" name="section" value="{{$section}}">

		<div class="panel-group" id="contact-edit-tools" role="tablist" aria-multiselectable="true">
			<div class="panel">
				<div id="perms-tool-collapse" class="panel-collapse collapse show" role="tabpanel" aria-labelledby="perms-tool">
					<div class="section-content-tools-wrapper">
						<div class="section-content-warning-wrapper">
						<p>{{$autolbl}}</p>
						<p>{{$permnote_self}}</p>
						</div>
						{{if $permcat_enable}}
						<a href="permcats" class="float-end"><i class="bi bi-plus-lg"></i>&nbsp;{{$permcat_new}}</a>
						{{include file="field_select.tpl" field=$permcat}}
						{{/if}}

						<div class="defperms-edit">
						{{foreach $perms as $prm}}
						{{include file="field_checkbox.tpl" field=$prm}}
						{{/foreach}}
						</div>

						{{if $hidden_perms}}
						{{foreach $hidden_perms as $prm}}
						<input type="hidden" name="{{$prm.0}}" value="{{$prm.1}}" >
						{{/foreach}}
						{{/if}}



						<div>
							<div class="section-content-info-wrapper">
							{{$autoapprove}}
							</div>
							{{include file="field_checkbox.tpl" field=$autoperms}}
						</div>

						<div class="settings-submit-wrapper" >
							<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
		</div>
		</form>
	</div>
</div>
