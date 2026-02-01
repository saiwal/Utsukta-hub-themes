<div id="adminpage" class="generic-content-wrapper-styled">
  <div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$title}}</span></div>
  </div>
  <div class="accordion accordion-flush" id="accordionExample">
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne"
          aria-expanded="true" aria-controls="collapseOne">
          {{$page}}
        </button>
      </h2>
      <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionExample">
        <div class="accordion-body">
          <form action="{{$baseurl}}/admin/site" method="post">
            <input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

            {{include file="field_input.tpl" field=$sitename}}
            {{include file="field_input.tpl" field=$location}}
            {{include file="field_textarea.tpl" field=$banner}}
            {{include file="field_textarea.tpl" field=$siteinfo}}
            {{include file="field_textarea.tpl" field=$admininfo}}
            {{include file="field_select.tpl" field=$access_policy}}
            {{include file="field_input.tpl" field=$reply_address}}
            {{include file="field_input.tpl" field=$from_email}}
            {{include file="field_input.tpl" field=$from_email_name}}
            {{include file="field_select.tpl" field=$theme}}
            {{* include file="field_select.tpl" field=$theme_mobile *}}
            {{include file="field_input.tpl" field=$frontpage}}
            {{include file="field_checkbox.tpl" field=$mirror_frontpage}}
            {{include file="field_checkbox.tpl" field=$login_on_homepage}}
            {{** include file="field_checkbox.tpl" field=$enable_context_help **}}
            {{if $directory_server}}
            {{include file="field_select.tpl" field=$directory_server}}
            {{/if}}

            <div class="submit buttons the-end">
							<button type="submit" name="page_site" class="flush" value="{{$submit}}" />{{$submit}}</button>
            </div>

        </div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo"
          aria-expanded="false" aria-controls="collapseTwo">
          {{$registration}}
        </button>
      </h2>
      <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
        <div class="accordion-body">
          {{include file="field_input.tpl" field=$register_text}}
          {{include file="field_select.tpl" field=$role}}
          {{include file="field_select.tpl" field=$register_policy}}
          {{** include file="field_checkbox.tpl" field=$register_wo_email **}}
          {{include file="register_duty.tpl" field=$register_duty}}
          {{include file="field_input.tpl" field=$register_perday}}
          {{include file="field_input.tpl" field=$register_sameip}}
          {{$reg_delay}}
          {{$reg_expire}}
          {{include file="field_checkbox.tpl" field=$reg_autochannel}}
          {{include file="field_checkbox.tpl" field=$invitation_only}}
          {{include file="field_checkbox.tpl" field=$invitation_also}}
          {{include file="field_checkbox.tpl" field=$verify_email}}

          {{include file="field_input.tpl" field=$minimum_age}}
          {{** include file="field_input.tpl" field=$sellpage **}}
          {{include file="field_input.tpl" field=$first_page}}

          <div class="submit buttons the-end">
						<button type="submit" name="page_site" class="flush" value="{{$submit}}" />{{$submit}}</button>
          </div>

        </div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
          data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
          {{$corporate}}
        </button>
      </h2>
      <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
        <div class="accordion-body">
          {{include file="field_checkbox.tpl" field=$feed_contacts}}
          {{include file="field_checkbox.tpl" field=$force_publish}}
          {{include file="field_checkbox.tpl" field=$disable_discover_tab}}
          {{include file="field_checkbox.tpl" field=$site_firehose}}
          {{include file="field_checkbox.tpl" field=$open_pubstream}}
          {{include file="field_textarea.tpl" field=$incl}}
          {{include file="field_textarea.tpl" field=$excl}}
          {{include file="field_input.tpl" field=$abandon_days}}

          <div class="submit buttons the-end">
						<button type="submit" name="page_site" class="flush" value="{{$submit}}" />{{$submit}}</button>
          </div>


        </div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
          data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
          {{$advanced}}
        </button>
      </h2>
      <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
        <div class="accordion-body">
          {{include file="field_checkbox.tpl" field=$sse_enabled}}
          {{include file="field_input.tpl" field=$imagick_path}}
          {{include file="field_input.tpl" field=$proxy}}
          {{include file="field_input.tpl" field=$proxyuser}}
          {{include file="field_input.tpl" field=$timeout}}
          {{**include file="field_input.tpl" field=$delivery_interval**}}
          {{include file="field_input.tpl" field=$delivery_batch_count}}
          {{**include file="field_input.tpl" field=$force_queue**}}
          {{**include file="field_input.tpl" field=$poll_interval**}}
          {{include file="field_input.tpl" field=$maxloadavg}}
          {{include file="field_input.tpl" field=$default_expire_days}}
          {{include file="field_input.tpl" field=$active_expire_days}}

         <div class="submit buttons the-end">
						<button type="submit" name="page_site" class="flush" value="{{$submit}}" />{{$submit}}</button>
          </div>

        </div>
      </div>
    </div>

  </div>
  </form>
</div>
{{*
COMMENTS for this template:
hilmar, 2020.01
script placed at the end
*}}
<script>
  $(function () {

    $("#cnftheme").colorbox({
      width: 800,
      onLoad: function () {
        var theme = $("#id_theme :selected").val();
        $("#cnftheme").attr('href', "{{$baseurl}}/admin/themes/" + theme);
      },
      onComplete: function () {
        $(this).colorbox.resize();
        $("#colorbox form").submit(function (e) {
          var url = $(this).attr('action');
          // can't get .serialize() to work...
          var data = {};
          $(this).find("input").each(function () {
            data[$(this).attr('name')] = $(this).val();
          });
          $(this).find("select").each(function () {
            data[$(this).attr('name')] = $(this).children(":selected").val();
          });
          console.log(":)", url, data);

          $.post(url, data, function (data) {
            if (timer) clearTimeout(timer);
            updateInit();
            $.colorbox.close();
          })

          return false;
        });

      }
    });
  });
</script>
