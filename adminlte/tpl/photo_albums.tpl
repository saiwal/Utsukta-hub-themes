<div id="side-bar-photos-albums" class="card mb-3">
	<div class="card-header">{{$title}}</div>
  <div class="card-body">
    <ul class="list-group list-group-flush" style="list-style: none;">
      <a class="list-group-item" href="{{$baseurl}}/photos/{{$nick}}" title="{{$title}}" >{{$recent}}</a>
      {{if $albums}}
      {{foreach $albums as $al}}
      {{if $al.shorttext}}
      <a class="list-group-item" href="{{$baseurl}}/photos/{{$nick}}/album/{{$al.bin2hex}}"><span class="badge bg-primary float-end">{{$al.total}}</span>{{$al.shorttext}}</a>
      {{/if}}
      {{/foreach}}
      {{/if}}
    </ul>
  </div>
</div>
