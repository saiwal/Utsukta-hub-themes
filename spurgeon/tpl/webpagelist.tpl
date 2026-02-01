<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		{{if $editor}}
		<div class="float-end">
			<button id="webpage-create-btn" class="btn btn-sm btn-success acl-form-trigger" onclick="openClose('webpage-editor');" data-form_id="profile-jot-form"><i class="bi bi-pencil-square-o"></i>&nbsp;{{$create}}</button>
		</div>
		{{/if}}
		<div class="h3 mt-0">{{$listtitle}}</div>
	</div>
	{{if $editor}}
	<div id="webpage-editor" class="section-content-tools-wrapper">
		{{$editor}}
	</div>
	{{/if}}
	{{if $pages}}
	<div id="pagelist-content-wrapper" class="section-content-wrapper-np">
		<table id="webpage-list-table" class="table-responsive">
      <thead>
			<tr>
				<th width="1%">{{$pagelink_txt}}</th>
				<th width="95%">{{$title_txt}}</th>
				<th width="1%"></th>
				<th width="1%"></th>
				<th width="1%"></th>
				<th width="1%"></th>
				<th width="1%" class="d-none d-md-table-cell">{{$created_txt}}</th>
				<th width="1%" class="d-none d-md-table-cell">{{$edited_txt}}</th>
			</tr>
      </thead>
      <tbody>
			{{foreach $pages as $key => $items}}
			{{foreach $items as $item}}
			<tr id="webpage-list-item-{{$item.url}}">
				<td>
					{{if $view}}
					<a href="page/{{$channel}}/{{$item.pageurl}}" title="{{$view}}">{{$item.pagetitle}}</a>
					{{else}}
					{{$item.pagetitle}}
					{{/if}}
				</td>
				<td>
					{{$item.title}}
				</td>
				<td class="webpage-list-tool dropdown">
					{{if $item.lockstate=='lock'}}
					<i class="bi bi-lock lockview" data-bs-toggle="dropdown" onclick="lockview('item',{{$item.url}});" ></i>
					<ul id="panel-{{$item.url}}" class="lockview-panel dropdown-menu"></ul>
					{{/if}}
				</td>
				<td class="webpage-list-tool">
					{{if $edit}}
					<a href="{{$baseurl}}/{{$item.url}}" title="{{$edit}}"><i class="bi bi-pencil"></i></a>
					{{/if}}
				</td>
				<td class="webpage-list-tool">
					{{if $item.bb_element}}
					<a href="rpost?attachment={{$item.bb_element}}" title="{{$share}}"><i class="bi bi-share"></i></a>
					{{/if}}
				</td>
				<td class="webpage-list-tool">
					{{if $edit}}
					<a href="#" title="{{$delete}}" onclick="dropItem('item/drop/{{$item.url}}', '#webpage-list-item-{{$item.url}}'); return false;"><i class="bi bi-trash drop-icons"></i></a>
					{{/if}}
				</td>
				<td class="d-none d-md-table-cell">
					<small class="autotime-narrow opacity-75" title="{{$item.created}}"></small>
				</td>
				<td class="d-none d-md-table-cell">
					<small class="autotime-narrow opacity-75" title="{{$item.edited}}"></small>
				</td>
			</tr>
			{{/foreach}}
			{{/foreach}}
      </tbody>
		</table>
	</div>
	{{/if}}
	<div class="clear"></div>
</div>

<script>
$(document).ready(function () {
updateRelativeTime('.autotime-narrow');
});
</script>
