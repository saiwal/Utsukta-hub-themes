<div class="generic-content-wrapper">
	<div class="section-title-wrapper clearfix app-content-header">
		{{if $is_owner}}
		<button type="button" class="btn btn-success btn-sm float-end acl-form-trigger" onclick="openClose('chatroom-new');" data-form_id="chatroom-new-form"><i class="bi bi-plus-lg"></i>&nbsp;{{$newroom}}</button>
		{{/if}}
		<h3>{{$header}}</h3>
	</div>
	{{if $is_owner}}
	{{$chatroom_new}}
	{{/if}}
	{{if $rooms}}
	<div class="section-content-wrapper-np">
    <div class="mb-3">
		<table id="chatrooms-index" class="table table-hover">
      <thead>
			<tr>
				<th width="97%">{{$name}}</th>
				<th width="1%">{{$expire}}</th>
				<th width="1%" class="chatrooms-index-tool"></th>
				<th width="1%"></th>
			</tr>
      </thead>
			{{foreach $rooms as $room}}
			<tr class="chatroom-index-row">
				<td><a href="{{$baseurl}}/chat/{{$nickname}}/{{$room.cr_id}}">{{$room.cr_name}}</a></td>
				<td>{{$room.cr_expire}}&nbsp;min</td>
				<td class="chatrooms-index-tool{{if $room.allow_cid || $room.allow_gid || $room.deny_cid || $room.deny_gid}} dropdown float-end{{/if}}">
					{{if $room.allow_cid || $room.allow_gid || $room.deny_cid || $room.deny_gid}}
					<i class="bi bi-lock lockview" data-bs-toggle="dropdown" onclick="lockview('chatroom',{{$room.cr_id}});"></i>
					<ul id="panel-{{$room.cr_id}}" class="lockview-panel dropdown-menu"></ul>
					{{/if}}
				</td>
				<td><span class="badge bg-secondary">{{$room.cr_inroom}}</span></td>
			</tr>
			{{/foreach}}
		</table>

	</div>
	</div>
	{{else}}
	<div class="section-content-wrapper-np">
    <div class="mb-3">

		{{$norooms}}
	</div>
	</div>
	{{/if}}
</div>
