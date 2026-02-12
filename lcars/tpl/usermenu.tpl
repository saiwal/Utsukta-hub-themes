
{{if $wrap}}
<div id="pmenu-{{$id}}" class="pmenu{{if $class}} {{$class}}{{/if}} mb-3">

{{if $menu.menu_desc}}
	<div class="lcars-text-bar">
		<span>
			{{$menu.menu_desc}}
			{{if $edit}}
				<a href="mitem/{{$nick}}/{{$menu.menu_id}}" title="{{$edit}}">
					<i class="bi bi-pencil fakelink ms-2"></i>
				</a>
			{{/if}}
		</span>
	</div>
{{/if}}
{{/if}}


{{if $items}}
<div class="pmenu-body pillbox">

	{{* -------- FIRST PASS: ALL BUTTONS -------- *}}
	{{foreach $items as $mitem}}
		{{assign var=subid value="submenu-"|cat:$mitem.mitem_id}}

		{{if $mitem.submenu}}
			<button
				id="pmenu-item-{{$mitem.mitem_id}}"
				class="pill"
				data-bs-toggle="collapse"
				data-bs-target="#{{$subid}}"
				aria-expanded="false"
				aria-controls="{{$subid}}">
				<span>{{$mitem.mitem_desc}}</span>
				<i class="bi bi-chevron-down small"></i>
			</button>
		{{else}}
			<a  id="pmenu-item-{{$mitem.mitem_id}}"
				href="{{$mitem.mitem_link}}"
				class="pill"
				{{if $mitem.newwin}}target="_blank"{{/if}}
				rel="nofollow noopener">
				{{$mitem.mitem_desc}}
			</a>
		{{/if}}

	{{/foreach}}

</div>

	{{* -------- SECOND PASS: ALL COLLAPSES -------- *}}
	{{foreach $items as $mitem}}
		{{if $mitem.submenu}}
			{{assign var=subid value="submenu-"|cat:$mitem.mitem_id}}
			<div class="collapse mt-2" id="{{$subid}}">
				{{$mitem.submenu}}
			</div>
		{{/if}}
	{{/foreach}}

{{/if}}


{{if $wrap}}
	<div class="pmenu-end"></div>
</div>
{{/if}}
