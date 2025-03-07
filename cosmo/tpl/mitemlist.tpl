<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="float-end">
			<button id="webpage-create-btn" class="btn btn-sm btn-success" onclick="openClose('menu-element-creator');"><i class="bi bi-pencil-square-o"></i>&nbsp;{{$hintnew}}</button>
		</div>
		<h3>{{$title}} {{if $menudesc}}{{$menudesc}}{{else}}{{$menuname}}{{/if}}</h3>
		<div class="clear"></div>
	</div>

	{{$create}}

	{{if $mlist }}
	<div id="mitemlist-content-wrapper" class="section-content-wrapper-np">
		<table id="mitem-list-table">
			<tr>
				<th width="1%">{{$nametitle}}</th>
				<th width="96%">{{$targettitle}}</th>
				<th width="1%"></th>
				<th width="1%"></th>
				<th width="1%"></th>
			</tr>
			{{foreach $mlist as $m }}
			<tr id="mitem-list-item-{{$m.mitem_id}}">
				<td width="1%">{{$m.mitem_desc}}</td>
				<td width="96%"><a href="{{$m.mitem_link}}">{{$m.mitem_link}}</a></td>
				<td width="1%" class="mitem-list-tool dropdown">{{if $m.allow_cid || $m.allow_gid || $m.deny_cid || $m.deny_gid}}<i class="bi bi-lock lockview" data-bs-toggle="dropdown" onclick="lockview('menu_item',{{$m.mitem_id}});" ></i><ul id="panel-{{$m.mitem_id}}" class="lockview-panel dropdown-menu"></ul>{{/if}}</td>
				<td width="1%" class="mitem-list-tool"><a href="mitem/{{$nick}}/{{$menu_id}}/{{$m.mitem_id}}" title="{{$hintedit}}"><i class="bi bi-pencil"></i></a></td>
				<td width="1%" class="mitem-list-tool"><a href="#" title="{{$hintdrop}}"  onclick="dropItem('mitem/{{$nick}}/{{$menu_id}}/{{$m.mitem_id}}/drop', '#mitem-list-item-{{$m.mitem_id}}, #pmenu-item-{{$m.mitem_id}}'); return false;"><i class="bi bi-trash drop-icons"></i></a></td>
			</tr>
			{{/foreach}}
		</table>
	</div>
	{{/if}}
</div>
