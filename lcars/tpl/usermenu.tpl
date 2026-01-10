{{if $wrap}}
<div id="pmenu-{{$id}}" class="pmenu{{if !$class}} {{else}} {{$class}}{{/if}} mb-3">
  {{/if}}
  {{if $menu.menu_desc}}
	<div class="lcars-text-bar"><span>
    {{$menu.menu_desc}}{{if $edit}} <a href="mitem/{{$nick}}/{{$menu.menu_id}}"
        title="{{$edit}}"><i class="bi bi-pencil fakelink ms-2" title="{{$edit}}"></i></a>{{/if}}
		</span>
  </div>
  {{/if}}
    {{if $items}}
    <ul class="pmenu-body{{if $wrap || !$class}} flex-column{{elseif !$wrap || $class}} {{$class}}{{/if}}" style="list-style: none;">
      {{foreach $items as $mitem }}
      <li id="pmenu-item-{{$mitem.mitem_id}}" class="nav-item pmenu-item{{if $mitem.submenu}} dropdown{{/if}}">
        <a href="{{if $mitem.submenu}}#{{else}}{{$mitem.mitem_link}}{{/if}}"
          class="nav-link {{if $mitem.submenu}} dropdown-toggle{{/if}}" {{if $mitem.submenu}} data-bs-toggle="dropdown"
          {{/if}}{{if $mitem.newwin}}target="_blank" {{/if}} rel="nofollow noopener">{{$mitem.mitem_desc}}{{if
          $mitem.submenu}}<span class="caret"></span>{{/if}}</a>
        {{if $mitem.submenu}}{{$mitem.submenu}}{{/if}}
      </li>
      {{/foreach }}
    </ul>
    {{/if}}
    {{if $wrap}}
    <div class="pmenu-end"></div>
</div>
{{/if}}
