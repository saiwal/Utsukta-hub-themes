	<div class="tagblock card mb-3">
    <div class="card-header">{{$title}} </div>
    <div class="tags card-body" align="center">
      {{foreach $tags as $tag}}
      <span class="tag{{$tag[2]}}">#</span><a href="{{$baseurl}}{{$tag[0]}}" class="tag{{$tag}}">{{$tag[0]}}</a>
      {{/foreach}}
		</div>
    </div>


