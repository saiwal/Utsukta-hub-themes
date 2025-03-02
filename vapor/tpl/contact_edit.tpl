<form id="contact-edit-form" action="contactedit/{{$contact_id}}" method="post" >
  <div id="contact-edit-tools" class="accordion"  role="tablist" >
			<div class="accordion-item" role="tab" id="roles-tool">
				<h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#roles-tool-collapse" aria-expanded="true" aria-controls="roles-tool-collapse">          
						{{$roles_label}}
					</button>
				</h2>
			<div id="roles-tool-collapse" class="accordion-collapse collapse {{if $section == 'roles'}} show{{/if}}" role="tabpanel" aria-labelledby="roles-tool" data-bs-parent="#contact-edit-tools">
				<div class="section-content-tools-wrapper accordion-body">
					{{include file="field_select.tpl" field=$permcat}}
					<button class="btn btn-outline-secondary btn-sm float-end sub_section{{if $sub_section == 'perms'}} sub_section_active{{/if}}" type="button" onclick="openClose('perms-table', 'table')" data-section="perms">
						{{$compare_label}}
					</button>
					<a href="permcats/{{$permcat_value}}" class="btn btn-sm btn-outline-primary">
						<i class="bi bi-box-arrow-up-right"></i>&nbsp;{{$permcat_new}}
					</a>
					<table id="perms-table" class="table table-hover table-sm mt-3" style="display: {{if $sub_section == 'perms'}}table{{else}}none{{/if}};">
						<thead>
							<tr class="w-100">
								<th scope="col">{{$permission_label}}</th>
								<th scope="col">{{$them}}</th>
								<th scope="col">{{$me}}</th>
							</tr>
						</thead>
						<tbody>
							{{foreach $perms as $perm}}
							<tr>
								<td>{{$perm.1}}</td>
								<td>
									{{if $perm.2}}
									<i class="bi bi-check-lg text-success"></i>
									{{else}}
									<i class="bi bi-x-lg text-danger"></i>
									{{/if}}
								</td>
								<td>
									{{if $perm.3}}
									<i class="bi bi-check-lg text-success"></i>
									{{else}}
									<i class="bi bi-x-lg text-danger"></i>
									{{/if}}
								</td>
							</tr>
							{{/foreach}}

						</tbody>
					</table>
				</div>
			</div>
			</div>
		{{if $groups}}
			<div class="accordion-item" role="tab" id="group-tool">
        <h2 class="accordion-header">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#group-tool-collapse" aria-expanded="true" aria-controls="group-tool-collapse">
						{{$pgroups_label}}
					</button>
				</h2>
			<div id="group-tool-collapse" class="accordion-collapse collapse{{if $section == 'group'}} show{{/if}}" role="tabpanel" aria-labelledby="group-tool" data-bs-parent="#contact-edit-tools">
				<div class="section-content-tools-wrapper accordion-body">
					{{foreach $groups as $group}}
					{{include file="field_checkbox.tpl" field=$group}}
					{{/foreach}}
					<a href="group/new" class="btn btn-sm btn-outline-primary">
						<i class="bi bi-box-arrow-up-right"></i>&nbsp;{{$pgroups_label}}
					</a>
				</div>
			</div>
			</div>
		{{/if}}
		{{if $multiprofs}}
			<div class="accordion-item" role="tab" id="profile-tool">
				<h2 class="accordion-header">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#profile-tool-collapse" aria-expanded="true" aria-controls="profile-tool-collapse">
						{{$profiles_label}}
					</button>
				</h2>
			<div id="profile-tool-collapse" class="accordion-collapse collapse{{if $section == 'profile'}} show{{/if}}" role="tabpanel" aria-labelledby="profile-tool" data-bs-parent="#contact-edit-tools">
				<div class="section-content-tools-wrapper accordion-body">
					{{$profile_select}}
					<a href="profiles" class="btn btn-sm btn-outline-primary">
						<i class="bi bi-box-arrow-up-right"></i>&nbsp;{{$profiles_label}}
					</a>
				</div>
			</div>
			</div>
		{{/if}}
		{{if $slide}}
			<div class="accordion-item" role="tab" id="affinity-tool">
				<h2 class="accordion-header">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#affinity-tool-collapse" aria-expanded="true" aria-controls="affinity-tool-collapse">
						{{$affinity_label}}
					</button>
				</h2>
			<div id="affinity-tool-collapse" class="accordion-collapse collapse{{if $section == 'affinity'}} show{{/if}}" role="tabpanel" aria-labelledby="affinity-tool" data-bs-parent="#contact-edit-tools">
				<div class="section-content-tools-wrapper accordion-body">
						<div class="mb-2"><label>{{$lbl_slider}}</label></div>
						{{$slide}}
						<input id="contact-closeness-mirror" type="hidden" name="closeness" value="{{$close}}" />
				</div>
			</div>
			</div>
		{{/if}}
		{{if $connfilter}}
			<div class="accordion-item" role="tab" id="filter-tool">
				<h2 class="accordion-header">
					<button class="accordion-button collapsed" type="button"  data-bs-toggle="collapse" data-bs-target="#filter-tool-collapse" aria-expanded="true" aria-controls="filter-tool-collapse">
						{{$filter_label}}
					</button>
				</h2>
			<div id="filter-tool-collapse" class="accordion-collapse collapse{{if $section == 'filter'}} show{{/if}}" role="tabpanel" aria-labelledby="filter-tool" data-bs-parent="#contact-edit-tools">
				<div class="section-content-tools-wrapper accordion-body">
					{{include file="field_textarea.tpl" field=$incl}}
					{{include file="field_textarea.tpl" field=$excl}}
				</div>
			</div>
			</div>
		{{else}}
		<input type="hidden" name="{{$incl.0}}" value="{{$incl.2}}" />
		<input type="hidden" name="{{$excl.0}}" value="{{$excl.2}}" />
		{{/if}}
	</div>
</form>
