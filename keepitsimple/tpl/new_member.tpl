  <div class="h6">
    {{$title}}
      </div>
  <!-- /.card-header -->
  {{if $options}}
    <div class="accordion accordion-flush" id="accordion_{{$title|replace:' ':'_'}}">
    {{assign var=prev_title value=''}}
    {{foreach $options as $key => $value}}
    {{if is_array($value)}}
    {{* Nested array → use prev_title as header *}}
    {{assign var=uid value=$title|cat:'_'|cat:$key|replace:' ':'_'}}
    <div class="accordion-item">
      <div class="accordion-header m-0" id="heading_{{$uid}}">
        <button class="h6 accordion-button collapsed m-0 text-wrap" type="button" data-bs-toggle="collapse"
          data-bs-target="#collapse_{{$uid}}" aria-expanded="false" aria-controls="collapse_{{$uid}}">
          {{$prev_title}}
        </button>
      </div>
      <div id="collapse_{{$uid}}" class="accordion-collapse collapse" aria-labelledby="heading_{{$uid}}"
        data-bs-parent="#accordion_{{$title|replace:' ':'_'}}">
        <div class="accordion-body p-3">
          <ul class="list-group list-group-flush">
            {{foreach $value as $subkey => $subval}}
            <li class="list-group-item list-group-item-action">
              <a href="{{$subkey}}">{{$subval}}</a>
            </li>
            {{/foreach}}
          </ul>
        </div>
      </div>
    </div>
    {{else}}
    {{* Simple string → store as prev_title *}}
    {{assign var=prev_title value=$value}}
    {{/if}}
    {{/foreach}}
  </div>
  {{/if}}
