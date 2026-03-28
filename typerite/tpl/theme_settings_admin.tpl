<h4>{{$title}}</h4>

<form action="admin/themes/typerite" method="post">
    <input type="hidden" name="form_security_token" value="{{$form_security_token}}" />

    <div class="section-content-wrapper">
        <!-- SUBMIT -->
        <div class="form-group">
            <input type="submit" name="typerite-settings-submit" class="btn btn-primary" value="{{$submit}}" />
        </div>

    </div>
</form>
