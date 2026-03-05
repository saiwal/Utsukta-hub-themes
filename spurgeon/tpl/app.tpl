<div class="entry__author-box position-relative">
	<figure class="entry__author-avatar">
		{{if $icon}}
			<i class="app-icon bi bi-{{$icon}} display-1"></i>
			{{else}}
			<img alt="" src="{{$app.photo}}" class="avatar">
			{{/if}}
	</figure>
	<div class="entry__author-info">
		<h5 class="entry__author-name">
			<a href="{{$app.url}}">
				{{$app.name}}
			</a>
		</h5>
		<p>
		{{if $app.desc}}{{$app.desc}}{{/if}}
		</p>
	</div>
	<div class="position-absolute top-0 end-0 pt-5">
		{{if $app.type !== 'system'}}
		{{if $purchase}}
		<div class="app-purchase">
			<a href="{{$app.page}}" class="btn btn-secondary" title="{{$purchase}}"><i
					class="bi bi-box-arrow-up-right"></i></a>
		</div>
		{{/if}}
		{{if $action_label || $update || $delete || $feature}}
		<div class="app-tools">
			<form action="{{$hosturl}}appman" method="post">
				<input type="hidden" name="papp" value="{{$app.papp}}" />
				{{if $action_label}}<button type="submit" name="install" value="{{$action_label}}"
					class="btn btn-{{if $installed}}outline-secondary{{else}}success{{/if}} btn-sm" title="{{$action_label}}"><i
						class="bi{{if $installed}} bi-arrow-repeat{{else}} bi-arrow-down-circle{{/if}}"></i>
					{{$action_label}}</button>{{/if}}
				{{if $edit}}<input type="hidden" name="appid" value="{{$app.guid}}" /><button type="submit" name="edit"
					value="{{$edit}}" class="btn btn-secondary btn-sm" title="{{$edit}}"><i
						class="bi bi-pencil"></i></button>{{/if}}
				{{if $delete}}<button type="submit" name="delete" value="{{if $deleted}}{{$undelete}}{{else}}{{$delete}}{{/if}}"
					class="btn btn-secondary btn-sm" title="{{if $deleted}}{{$undelete}}{{else}}{{$delete}}{{/if}}"><i
						class="bi bi-trash drop-icons"></i></button>{{/if}}
				{{if $settings_url}}<a href="{{$settings_url}}/?f=&rpath={{$rpath}}" class="btn btn-secondary btn-sm"><i
						class="bi bi-gear"></i></a>{{/if}}
				{{if $feature}}<button type="submit" name="feature" value="nav_featured_app" class="btn btn-secondary btn-sm"
					title="{{if $featured}}{{$remove}}{{else}}{{$add}}{{/if}}"><i
						class="bi bi-star{{if $featured}}-fill text-success{{/if}}"></i></button>{{/if}}
				{{if $pin}}<button type="submit" name="pin" value="nav_pinned_app" class="btn btn-secondary btn-sm"
					title="{{if $pinned}}{{$remove_nav}}{{else}}{{$add_nav}}{{/if}}"><i
						class="bi bi-pin{{if $pinned}}-fill text-success{{/if}}"></i></button>{{/if}}
			</form>
		</div>
		{{/if}}
		{{/if}}
	</div>
</div>
