{{if $wrap}}
	{{if $menu.menu_desc}}
	<div class="h5">{{$menu.menu_desc}}</div>
	{{/if}}
{{/if}}

{{if $items}}

	{{if $wrap}}
	<ul class="nav flex-column sidebar-menu text-dark " id="navigation" role="navigation" aria-label="Main navigation">
	{{else}}
	<ul class="nav flex-column ms-3 border-start border-dark border-4" role="navigation" aria-label="Navigation sub menu">
	{{/if}}

	{{foreach $items as $mitem }}

		<li class="nav-item" id="pmenu-item-{{$mitem.mitem_id}}">

			{{if $mitem.submenu}}

				<a class="nav-link link-dark d-flex justify-content-between align-items-center"
				   data-bs-toggle="collapse"
				   href="#submenu-{{$mitem.mitem_id}}"
				   role="button"
				   aria-expanded="false"
				   aria-controls="submenu-{{$mitem.mitem_id}}">
					<span>
						<i class="nav-icon bi bi-link me-2"></i>
						{{$mitem.mitem_desc}}
					</span>
					<i class="bi bi-chevron-down"></i>
				</a>

				<div class="collapse" id="submenu-{{$mitem.mitem_id}}">
					{{$mitem.submenu}}
				</div>

			{{else}}

				<a href="{{$mitem.mitem_link}}"
				   class="nav-link link-dark"
				   {{if $mitem.newwin}}target="_blank"{{/if}}>
					<i class="nav-icon bi bi-link me-2"></i>
					{{$mitem.mitem_desc}}
				</a>

			{{/if}}

		</li>

	{{/foreach}}

	</ul>
{{/if}}

