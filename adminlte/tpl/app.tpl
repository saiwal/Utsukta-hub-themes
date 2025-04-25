<div class="card mb-3">
<div class="section-subtitle-wrapper clearfix card-header pb-0 border-bottom-0">
	<div class="float-end">
		{{if $app.type !== 'system'}}
		{{if $purchase}}
		<div class="app-purchase">
			<a href="{{$app.page}}" class="btn btn-outline-secondary" title="{{$purchase}}" ><i class="bi bi-box-arrow-up-right"></i></a>
		</div>
		{{/if}}
		{{if $action_label || $update || $delete || $feature}}
		<div class="app-tools">
			<form action="{{$hosturl}}appman" method="post">
			<input type="hidden" name="papp" value="{{$app.papp}}" />
			{{if $action_label}}<button type="submit" name="install" value="{{$action_label}}" class="btn btn-{{if $installed}}outline-secondary{{else}}success{{/if}} btn-sm" title="{{$action_label}}" ><i class="bi{{if $installed}} bi-arrow-repeat{{else}} bi-arrow-down-circle{{/if}}" ></i> {{$action_label}}</button>{{/if}}
			{{if $edit}}<input type="hidden" name="appid" value="{{$app.guid}}" /><button type="submit" name="edit" value="{{$edit}}" class="btn btn-outline-secondary btn-sm" title="{{$edit}}" ><i class="bi bi-pencil" ></i></button>{{/if}}
			{{if $delete}}<button type="submit" name="delete" value="{{if $deleted}}{{$undelete}}{{else}}{{$delete}}{{/if}}" class="btn btn-outline-secondary btn-sm" title="{{if $deleted}}{{$undelete}}{{else}}{{$delete}}{{/if}}" ><i class="bi bi-trash drop-icons"></i></button>{{/if}}
			{{if $settings_url}}<a href="{{$settings_url}}/?f=&rpath={{$rpath}}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i></a>{{/if}}
			{{if $feature}}<button type="submit" name="feature" value="nav_featured_app" class="btn btn-outline-secondary btn-sm" title="{{if $featured}}{{$remove}}{{else}}{{$add}}{{/if}}"><i class="bi bi-star{{if $featured}}-fill text-success{{/if}}"></i></button>{{/if}}
			{{if $pin}}<button type="submit" name="pin" value="nav_pinned_app" class="btn btn-outline-secondary btn-sm" title="{{if $pinned}}{{$remove_nav}}{{else}}{{$add_nav}}{{/if}}"><i class="bi bi-pin{{if $pinned}}-fill text-success{{/if}}"></i></button>{{/if}}
			</form>
		</div>
		{{/if}}
		{{/if}}
	</div>
    <div><a href="{{$app.url}}">{{$app.name}}{{if $app.price}} ({{$app.price}}){{/if}}</a></div>
</div>
<div class="section-content-tools-wrapper container card-body">
	<div class="{{if $deleted}} app-deleted{{/if}} mb-3">
		<a class="app-icon app-link" href="{{$app.url}}"{{if $app.target}} target="{{$app.target}}"{{/if}}{{if $installed}} data-papp="{{$app.papp}}" data-icon="{{$icon}}" data-url="{{$app.url}}" data-name="{{$app.name}}"{{/if}}>
			{{if $icon}}
			<i class="app-icon bi bi-{{$icon}} fs-1"></i>
			{{else}}
			<img src="{{$app.photo}}" width="80" height="80" />
			{{/if}}
		</a>
		<div class="app-info">
			{{if $app.desc}}{{$app.desc}}{{/if}}
		</div>
	</div>
</div>

</div>
