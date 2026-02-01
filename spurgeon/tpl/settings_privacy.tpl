<div class="generic-content-wrapper ">
	<div class="h3 mt-0">{{$ptitle}}</div>
    {{$nickname_block}}
    <form action="settings/privacy" id="settings-form" method="post" autocomplete="off">
      <input type='hidden' name='form_security_token' value='{{$form_security_token}}' />

      <div class="section-content-tools-wrapper">

        {{include file="field_checkbox.tpl" field=$index_opt_out}}
        {{include file="field_checkbox.tpl" field=$autoperms}}
        {{include file="field_checkbox.tpl" field=$permit_all_mentions}}
        {{include file="field_checkbox.tpl" field=$moderate_unsolicited_comments}}
        {{include file="field_checkbox.tpl" field=$ocap_enabled}}

        {{if $sec_addon}}
        {{$sec_addon}}
        {{/if}}
        {{if $permission_limits}}
        <div id="permission-limits">
          <div class="modal" id="apsModal">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <div class="modal-title h5">{{$permission_limits_label}}</div>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div class="multi-collapse collapse show">
                    <h2 class="text-danger mb-3"><i class="bi fa-warning"></i> {{$permission_limits_warning.0}}</h2>
                    <h3 class="mb-3">{{$permission_limits_warning.1}}</h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="collapse"
                      data-bs-target=".multi-collapse" aria-expanded="false"
                      aria-controls="collapseExample">{{$permission_limits_warning.2}}</button>
                  </div>
                  <div class="multi-collapse collapse">
                    {{foreach $permiss_arr as $permit}}
                    {{include file="field_select.tpl" field=$permit}}
                    {{/foreach}}
                    {{include file="field_checkbox.tpl" field=$group_actor}}
                  </div>

                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
              </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
          </div><!-- /.modal -->
        </div>
        <div class="float-end">
          <button type="button" class="btn btn-secondary" data-bs-toggle="modal"
            data-bs-target="#apsModal">{{$permission_limits_label}}</button>
        </div>
        {{/if}}
        <div class="settings-submit-wrapper">
          <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
        </div>
      </div>
    </form>
</div>
