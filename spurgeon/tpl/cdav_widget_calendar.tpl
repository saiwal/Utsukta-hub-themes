<div class="mb-3">
  <div class="h4">{{$channel_calendars_label}}</div>
	{{foreach $channel_calendars as $channel_calendar}}
	<div id="calendar-{{$channel_calendar.calendarid}}">
		<div class="ml-3{{if !$channel_calendar@last}} mb-3{{/if}} h6 mt-0">
			<i id="calendar-btn-{{$channel_calendar.calendarid}}" class="pe-2 bi {{if $channel_calendar.switch}}bi-calendar-check{{else}}bi-calendar{{/if}} generic-icons-nav cursor-pointer" onclick="add_remove_json_source('{{$channel_calendar.json_source}}', '{{$channel_calendar.color}}', {{$channel_calendar.editable}})" style="color: {{$channel_calendar.color}};"></i>{{$channel_calendar.displayname}}
			<div class="float-end">
				<a class="text-reset" href="#" onclick="exportDate(); return false;"><i id="download-icon" class="bi bi-download cursor-pointer generic-icons-right"></i></a>
			</div>
		</div>
	</div>
	{{/foreach}}
</div>

{{if $my_calendars}}
<div class="mb-3">
  <div class="h3">{{$my_calendars_label}}</div>
	{{foreach $my_calendars as $calendar}}
	<div id="calendar-{{$calendar.calendarid}}">
		<div class="ml-3{{if !$calendar@last}} mb-3{{/if}} h6 mt-0">
			<i id="calendar-btn-{{$calendar.calendarid}}" class="pe-2 bi {{if $calendar.switch}}bi-calendar-check{{else}}bi-calendar{{/if}} generic-icons-nav cursor-pointer" onclick="add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{$calendar.editable}})" style="color: {{$calendar.color}};"></i>{{$calendar.displayname}}
			<div class="float-end">
				<i id="edit-icon" class="pe-2 bi bi-pencil cursor-pointer generic-icons-right" onclick="openClose('edit-calendar-{{$calendar.calendarid}}')"></i>
				<a class="text-reset" href="/cdav/calendars/{{$calendar.ownernick}}/{{$calendar.uri}}/?export"><i id="download-icon" class="pe-2 bi bi-download cursor-pointer generic-icons-right"></i></a>
				<i id="share-icon" class="pe-2 bi bi-share cursor-pointer generic-icons-right" onclick="openClose('share-calendar-{{$calendar.calendarid}}')"></i>
				<a class="text-reset" href="#" onclick="var drop = dropItem('/cdav/calendar/drop/{{$calendar.calendarid}}/{{$calendar.instanceid}}', '#calendar-{{$calendar.calendarid}}'); if(drop) { add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{$calendar.editable}}, 'drop'); } return false;"><i class="pe-2 bi bi-trash generic-icons-right"></i></a>
			</div>
			<div id="share-calendar-{{$calendar.calendarid}}" class="sub-menu" style="display: none; border-color: {{$calendar.color}};">
				{{if $calendar.sharees}}
				{{foreach $calendar.sharees as $sharee}}
				<div id="sharee-{{$calendar.calendarid}}-{{$sharee@iteration}}" class="mb-3">
					<i class="bi bi-share generic-icons-right"></i>{{$sharee.name}}&nbsp;{{$sharee.access}}
					<div class="float-end">
						<a class="text-reset" href="#" onclick="dropItem('/cdav/calendar/dropsharee/{{$calendar.calendarid}}/{{$calendar.instanceid}}/{{$sharee.hash}}', '#sharee-{{$calendar.calendarid}}-{{$sharee@iteration}}'); return false;"><i class="bi bi-trash generic-icons-right"></i></a>
					</div>
				</div>
				{{/foreach}}
				{{/if}}
				<form method="post" action="">
					<label for="share-{{$calendar.calendarid}}">{{$share_label}}</label>
					<input name="calendarid" type="hidden" value="{{$calendar.calendarid}}">
					<input name="instanceid" type="hidden" value="{{$calendar.instanceid}}">
					<div class="mb-3">
						<select id="share-{{$calendar.calendarid}}" name="sharee" class="form-control">
							{{$sharee_options}}
						</select>
					</div>
					<div class="mb-3">
						<select name="access" class="form-control">
							{{$access_options}}
						</select>
					</div>
					<div class="mb-3">
						<button type="submit" name="share" value="share" class="btn btn-primary btn-sm">{{$share}}</button>
					</div>
				</form>
			</div>
			<div id="edit-calendar-{{$calendar.calendarid}}" class="sub-menu" style="display: none; border-color: {{$calendar.color}};">
				<form id="edit-calendar-{{$calendar.calendarid}}" method="post" action="" class="colorpicker-component color-edit">
					<input id="id-{{$calendar.calendarid}}" name="id" type="hidden" value="{{$calendar.calendarid}}:{{$calendar.instanceid}}">
					<input id="color-{{$calendar.calendarid}}" name="color" type="hidden" value="{{$calendar.color}}" class="color-edit-input">
					<label for="edit-form-{{$calendar.calendarid}}">{{$edit_label}}</label>
					<div id="edit-form-{{$calendar.calendarid}}" class="input-group mb-3">
						<input id="create-{{$calendar.calendarid}}" name="{DAV:}displayname" type="text" value="{{$calendar.displayname}}" class="form-control mb-0">
						<div class="input-group-addon p-3"></div>
					</div>
					<div class="mb-3">
						<button type="submit" name="edit" value="edit" class="btn btn-primary btn-sm">{{$edit}}</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	{{/foreach}}
</div>
{{/if}}

{{if $shared_calendars}}
<div class="mb-3">
  <div class="h4">{{$shared_calendars_label}}</div>
	{{foreach $shared_calendars as $calendar}}
	<div id="shared-calendar-{{$calendar.calendarid}}" class="ml-3{{if !$calendar@last}} mb-3{{/if}} h6 mt-0">
		<i id="calendar-btn-{{$calendar.calendarid}}" class="pe-2 bi {{if $calendar.switch}}{{if $calendar.access == 'read-write'}}bi-calendar-check{{else}}bi-calendar-x{{/if}}{{else}}bi-calendar{{/if}} generic-icons-nav cursor-pointer" onclick="add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{$calendar.editable}}, {{if $calendar.access == 'read-write'}}'bi-calendar-check'{{else}}'bi-calendar-x'{{/if}})"  style="color: {{$calendar.color}};"></i>{{$calendar.displayname}} ({{$calendar.sharer}})
		<div class="float-end">
			<a class="text-reset" href="/cdav/calendars/{{$calendar.ownernick}}/{{$calendar.uri}}/?export"><i id="download-icon" class="bi bi-download cursor-pointer generic-icons-right"></i></a>
			<a class="text-reset" href="#" onclick="var drop = dropItem('/cdav/calendar/drop/{{$calendar.calendarid}}/{{$calendar.instanceid}}', '#shared-calendar-{{$calendar.calendarid}}'); if(drop) { add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{$calendar.editable}}, 'drop'); } return false;"><i class="bi bi-trash generic-icons-right"></i></a>
		</div>
	</div>
	{{/foreach}}
</div>
{{/if}}

<div class="mb-3">
	<div class="h3">{{$tools_label}}</div>
	<div class="nav nav-pills flex-column">
		<li class="nav-item">
			<a class="nav-link text-reset h6 mt-0" href="#" onclick="openClose('create-calendar'); return false;"><i class="bi bi-calendar-plus generic-icons-nav"></i> {{$create_label}}</a>
		</li>
		<div id="create-calendar" class="sub-menu-wrapper">
			<div class="sub-menu">
				<form method="post" action="" class="colorpicker-component color-edit">
					<input id="color" name="color" type="hidden" value="#ff8f00" class="color-edit-input">
					<div id="create-form" class="input-group mb-3">
						<input id="create" name="{DAV:}displayname" type="text" placeholder="{{$create_placeholder}}" class="form-control mb-0">
						<div class="input-group-addon p-3"></div>
					</div>
					<div class="mb-3">
						<button type="submit" name="create" value="create" class="btn btn-primary btn-sm">{{$create}}</button>
					</div>
				</form>
			</div>
		</div>
		<li class="h6 mt-0">
			<a class="nav-link text-reset" href="#" onclick="openClose('upload-form'); return false;"><i class="bi bi-upload generic-icons-nav"></i> {{$import_label}}</a>
		</li>
		<div id="upload-form" class="sub-menu-wrapper">
			<div class="sub-menu">
				<form enctype="multipart/form-data" method="post" action="">
					<div class="mb-3">
            <div class="ss-custom-select">
						<select id="import" name="target" class="u-fullwidth">
							<option value="">{{$import_placeholder}}</option>
							<optgroup label="{{$tools_options_label.0}}">
							<option value="{{$channel_calendar.calendarid}}">{{$channel_calendar.displayname}}</option>
							<optgroup label="{{$tools_options_label.1}}">
							{{foreach $writable_calendars as $writable_calendar}}
							<option value="{{$writable_calendar.id.0}}:{{$writable_calendar.id.1}}">{{$writable_calendar.displayname}}</option>
							{{/foreach}}
						</select>
            </div>
					</div>
					<div class="mb-3">
						<input class="form-control w-100" id="event-upload-choose" type="file" name="userfile" />
					</div>
					<button class="btn btn-primary btn-sm" type="submit" name="c_upload" value="c_upload">{{$upload}}</button>
				</form>
			</div>
		</div>
	</div>
</div>
