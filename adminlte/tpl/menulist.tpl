<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="float-end">
			<button id="webpage-create-btn" class="btn btn-sm btn-success" onclick="openClose('menu-creator');"><i class="bi bi-pencil-square-o"></i>&nbsp;{{$hintnew}}</button>
		</div>
		<h3>{{$title}}</h3>
		<div class="clear"></div>
	</div>

	{{$create}}

	{{if $menus }}
	<div id="menulist-content-wrapper" class="section-content-wrapper-np">
		<table id="menu-list-table">
			<tr>
				<th width="1%"></th>
				<th width="1%">{{$nametitle}}</th>
				<th width="93%">{{$desctitle}}</th>
				<th width="1%"></th>
				<th width="1%"></th>
				<th width="1%"></th>
				<th width="1%" class="d-none d-md-table-cell">{{$created}}</th>
				<th width="1%" class="d-none d-md-table-cell">{{$edited}}</th>
			</tr>
			{{foreach $menus as $m }}
			<tr id="menu-list-item-{{$m.menu_id}}">
				<td>{{if $m.bookmark}}<i class="bi fa-bookmark menu-list-tool" title="{{$bmark}}" ></i>{{/if}}</td>
				<td><a href="mitem/{{$nick}}/{{$m.menu_id}}{{if $sys}}?f=&sys=1{{/if}}" title="{{$hintcontent}}">{{$m.menu_name}}</a></td>
				<td>{{$m.menu_desc}}</td>
				<td class="menu-list-tool"><a href="menu/{{$nick}}/{{$m.menu_id}}{{if $sys}}?f=&sys=1{{/if}}" title="{{$hintedit}}"><i class="bi bi-pencil"></i></a></td>
				<td class="menu-list-tool"><a href="rpost?attachment={{$m.element}}" title="{{$share}}"><i class="bi bi-download"></i></a></td>
				<td class="menu-list-tool"><a href="#" title="{{$hintdrop}}"  onclick="dropItem('menu/{{$nick}}/{{$m.menu_id}}/drop{{if $sys}}?f=&sys=1{{/if}}', '#menu-list-item-{{$m.menu_id}}'); return false;"><i class="bi bi-trash drop-icons"></i></a></td>
				<td class="d-none d-md-table-cell">{{$m.menu_created}}</td>
				<td class="d-none d-md-table-cell">{{$m.menu_edited}}</td>
			</tr>
			{{/foreach}}
		</table>
	</div>
	{{/if}}
</div>
