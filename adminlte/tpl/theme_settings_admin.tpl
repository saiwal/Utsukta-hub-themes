<h4>{{$title}}</h4>

<form action="admin/themes/adminlte" method="post">
    <input type="hidden" name="form_security_token" value="{{$form_security_token}}" />

    <div class="section-content-wrapper">

        <!-- SCHEME -->
							{{include file="field_select.tpl" field=$schema}}
        <!-- LOGO -->
            {{include file="field_input.tpl" field=$logo}}
        <!-- DARK MODE -->
            {{include file="field_select.tpl" field=$dark_mode}}

        <!-- SIDEBAR MODE -->
  
            {{include file="field_select.tpl" field=$sidebar_mode}}

        <!-- BACKGROUND MODE -->
            {{include file="field_select.tpl" field=$bg_mode}}

        <!-- LIGHT BACKGROUND IMAGE -->
            {{include file="field_input.tpl" field=$background_image}}

        <!-- DARK BACKGROUND IMAGE -->
            {{include file="field_input.tpl" field=$background_image_dark}}
        <!-- LIGHT BACKGROUND COLOR -->
            {{include file="field_input.tpl" field=$background_color}}

        <!-- DARK BACKGROUND COLOR -->
            {{include file="field_input.tpl" field=$background_color_dark}}

        <!-- SUBMIT -->
        <div class="form-group">
            <input type="submit" name="adminlte-settings-submit" class="btn btn-primary" value="{{$submit}}" />
        </div>

    </div>
</form>
