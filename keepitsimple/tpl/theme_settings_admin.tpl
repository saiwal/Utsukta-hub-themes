<h4>{{$title}}</h4>

<form action="admin/themes/keepitsimple" method="post">
    <input type="hidden" name="form_security_token" value="{{$form_security_token}}" />

    <div class="section-content-wrapper">

            {{include file="field_input.tpl" field=$banner_image}}
        <!-- SUBMIT -->
        <div class="form-group">
            <input type="submit" name="keepitsimple-settings-submit" class="btn btn-primary" value="{{$submit}}" />
        </div>

    </div>
</form>
