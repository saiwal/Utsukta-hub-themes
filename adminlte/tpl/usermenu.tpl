{{if $wrap}}
<div class="card mb-3">
	{{if $menu.menu_desc}}
	<div class="card-header">{{$menu.menu_desc}}
	</div>
	{{/if}}
{{/if}}
{{if $items}}
	{{if $wrap}}
	<ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Main navigation" data-accordion="false" id="navigation">
	{{else}}
	<ul class="border-start border-4 nav nav-treeview ms-4" role="navigation" aria-label="Navigation sub menu" style="box-sizing: border-box; display: none;">
	{{/if}}
	{{foreach $items as $mitem }}
	<li class="nav-item" id="pmenu-item-{{$mitem.mitem_id}}">
		<a href="{{if $mitem.submenu}}#{{else}}{{$mitem.mitem_link}}{{/if}}" class="nav-link" {{if $mitem.newwin}}target="_blank" {{/if}}>
			<i class="nav-icon bi bi-circle"></i>
			<p>
			{{$mitem.mitem_desc}}
				{{if $mitem.submenu}}<i class="nav-arrow bi bi-chevron-right"></i>{{/if}}
			</p>
		</a>
		{{if $mitem.submenu}}{{$mitem.submenu}}{{/if}}
	{{/foreach}}
	</ul>
{{/if}}
{{if $wrap}}
</div>
{{/if}}
