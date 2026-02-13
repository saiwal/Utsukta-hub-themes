<div class="widget">
	<h6>{{$addressbooks_label}}</h6>
	{{foreach $addressbooks as $addressbook}}
	<div id="addressbook-{{$addressbook.id}}" class="ml-3">
		<div class="mb-3">
			<i class="bi bi-person generic-icons pe-2"></i><a href="/cdav/addressbook/{{$addressbook.id}}">{{$addressbook.displayname}}</a>
			<div class="float-end">
				<i id="edit-icon" class="bi bi-pencil fakelink generic-icons" onclick="openClose('edit-addressbook-{{$addressbook.id}}')"></i>
				<a href="/cdav/addressbooks/{{$addressbook.ownernick}}/{{$addressbook.uri}}/?export"><i id="download-icon" class="bi bi-download fakelink generic-icons"></i></a>
				<a href="#" onclick="dropItem('/cdav/addressbook/drop/{{$addressbook.id}}', '#addressbook-{{$addressbook.id}}'); return false;"><i class="bi bi-trash drop-icons"></i></a>
			</div>
		</div>
		<div id="edit-addressbook-{{$addressbook.id}}" class="sub-menu" style="display: none;">
			<form id="edit-addressbook-{{$addressbook.id}}" method="post" action="">
				<label for="edit-{{$addressbook.id}}">{{$edit_label}}</label>
				<div id="edit-form-{{$addressbook.id}}" class="mb-3">
					<input id="id-{{$addressbook.id}}" name="id" type="hidden" value="{{$addressbook.id}}">
					<input id="edit-{{$addressbook.id}}" name="{DAV:}displayname" type="text" value="{{$addressbook.displayname}}" class="form-control">
				</div>
				<div class="mb-3">
					<button type="submit" name="edit" value="edit" class="btn btn-primary btn-sm">{{$edit}}</button>
				</div>
			</form>
		</div>
	</div>
	{{/foreach}}
</div>

<div class="widget">
	<h6>{{$tools_label}}</h6>
	<ul class="nav nav-pills flex-column">
		<li class="nav-item">
			<a class="nav-link" href="#" onclick="openClose('create-addressbook'); return false;"><i class="bi bi-person-plus generic-icons pe-2"></i> {{$create_label}}</a>
		</li>
		<div id="create-addressbook" class="sub-menu-wrapper">
			<div class="sub-menu">
				<form method="post" action="">
					<div class="mb-3">
						<input id="create" name="{DAV:}displayname" type="text" placeholder="{{$create_placeholder}}" class="form-control mb-3">
						<button type="submit" name="create" value="create" class="btn btn-primary btn-sm">{{$create}}</button>
					</div>
				</form>
			</div>
		</div>
		<li class="nav-item">
			<a class="nav-link" href="#" onclick="openClose('upload-form'); return false;"><i class="bi bi-upload generic-icons"></i> {{$import_label}}</a>
		</li>
		<div id="upload-form" class="sub-menu-wrapper">
			<div class="sub-menu">
				<form enctype="multipart/form-data" method="post" action="">
					<div class="mb-3">
						<select id="import" name="target" class="form-control">
							<option value="">{{$import_placeholder}}</option>
							{{foreach $addressbooks as $addressbook}}
							<option value="{{$addressbook.id}}">{{$addressbook.displayname}}</option>
							{{/foreach}}
						</select>
					</div>
					<div class="mb-3">
						<input class="form-control-file w-100" id="addressbook-upload-choose" type="file" name="userfile" />
					</div>
					<button class="btn btn-primary btn-sm" type="submit" name="a_upload" value="a_upload">{{$upload}}</button>
				</form>
			</div>
		</div>
	</ul>
</div>
