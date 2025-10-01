<div class="generic-content-wrapper">
  <div class="card mb-3">
    <div class="card-header">{{$title}}</div>
    <div class="card-body">
      <form action="{{$action_url}}" method="post" autocomplete="off">
        <input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
        {{if $rpath}}
        <input type='hidden' name='rpath' value='{{$rpath}}'>
        {{/if}}
        {{foreach $features as $feature}}
        {{include file="field_checkbox.tpl" field=$feature}}
        {{/foreach}}
        {{if $extra_settings_html}}
        {{$extra_settings_html}}
        {{/if}}
        <div class="settings-submit-wrapper float-end">
          <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
        </div>
      </form>
    </div>
  </div>
</div>
