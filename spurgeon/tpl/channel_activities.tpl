    <h3>
      <i class="bi bi-{{$icon}} generic-icons-nav"></i> <a class="text-decoration-none" href="{{$url}}">{{$label}}</a>
    </h3>
    <div class="table-responsive">
        <table>
          <tbody>
	          {{foreach $items as $i}}
            <tr>
					      {{if $i.title}}
                <td><a href="{{$i.url}}" class="text-decoration-none">{{$i.title}}</a></td>
                {{/if}}
                <td><a href="{{$i.url}}" class="text-decoration-none">{{$i.summary}}</a></td>
				        <td class="text-muted autotime" title="{{$i.footer}}">{{$i.footer}}</td>
            </tr>
            {{/foreach}}
          </tbody>
        </table>
    </div>     


