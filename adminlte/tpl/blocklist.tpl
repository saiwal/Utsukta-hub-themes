<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		{{if $editor}}
		<div class="float-end">
			<button id="webpage-create-btn" class="btn btn-sm btn-success" onclick="openClose('block-editor');"><i class="bi bi-pencil-square-o"></i>&nbsp;{{$create}}</button>
		</div>
		{{/if}}
		<h3>{{$title}}</h3>
		<div class="clear"></div>
	</div>
	{{if $editor}}
	<div id="block-editor" class="section-content-tools-wrapper">
		{{$editor}}
	</div>
	{{/if}}
	{{if $pages}}
	<div id="pagelist-content-wrapper" class="section-content-wrapper-np">
		<table id="block-list-table">
			<tr>
				<th width="1%">{{$name}}</th>
				<th width="94%">{{$blocktitle}}</th>
				<th width="1%"></th>
				<th width="1%"></th>
				<th width="1%"></th>
				<th width="1%" class="d-none d-md-table-cell">{{$created}}</th>
				<th width="1%" class="d-none d-md-table-cell">{{$edited}}</th>
			</tr>
			{{foreach $pages as $key => $items}}
			{{foreach $items as $item}}
			<tr id="block-list-item-{{$item.url}}">
				<td>
					{{if $view}}
					<a href="block/{{$channel}}/{{$item.name}}" title="{{$view}}">{{$item.name}}</a>
					{{else}}
					{{$item.name}}
					{{/if}}
				</td>
				<td>
					{{$item.title}}
				</td>
				<td class="webpage-list-tool">
					{{if $edit}}
					<a href="{{$baseurl}}/{{$item.url}}" title="{{$edit}}"><i class="bi bi-pencil"></i></a>
					{{/if}}
				</td>
				<td class="webpage-list-tool">
					{{if $item.bb_element}}
					<a href="rpost?attachment={{$item.bb_element}}" title="{{$share}}"><i class="bi bi-download"></i></a>
					{{/if}}
				</td>
				<td class="webpage-list-tool">
					{{if $edit}}
					<a href="#" title="{{$delete}}" onclick="dropItem('item/drop/{{$item.url}}', '#block-list-item-{{$item.url}}'); return false;"><i class="bi bi-trash drop-icons"></i></a>
					{{/if}}
				</td>
				<td class="d-none d-md-table-cell">
					{{$item.created}}
				</td>
				<td class="d-none d-md-table-cell">
					{{$item.edited}}
				</td>
			</tr>
			{{/foreach}}
			{{/foreach}}
		</table>
	</div>
	<div class="clear"></div>
	{{/if}}
</div>
