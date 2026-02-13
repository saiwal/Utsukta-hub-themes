<div class="generic-content-wrapper">
		<header class="entry__header">
			<h2 class="entry__title h1">{{$title}}
			</h2>
		</header>
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
