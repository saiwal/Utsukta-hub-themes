<div class="h3">
  <i class="bi bi-{{$icon}} generic-icons-nav"></i> <a class="text-decoration-none link-dark " href="{{$url}}">{{$label}}</a>
</div>
      {{foreach $items as $i}}
      <dl>
				<dt class="d-flex justify-content-between">
        {{if $i.title}}
        <a href="{{$i.url}}" class="text-decoration-none">{{$i.title}}</a>
        {{/if}}
				<span class="text-muted autotime" title="{{$i.footer}}">{{$i.footer}}</span>
				</dt>
        <dd><a href="{{$i.url}}" class="link-primary text-decoration-none">{{$i.summary}}</a></dd>
      </dl>
      {{/foreach}}
