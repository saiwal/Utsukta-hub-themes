	<div class="dirtagblock card mb-3">
    <div class="card-header">{{$title}}</div>
    <div class="tags card-body" align="center">
      {{foreach $tags as $tag}}
      <span class="tags">#</span><a href="{{$baseurl}}{{$tag['term']}}" class="tag{{$tag['normalise']}}" rel="nofollow">{{$tag['term']}}</a>
      {{/foreach}}
		</div>
    </div>


