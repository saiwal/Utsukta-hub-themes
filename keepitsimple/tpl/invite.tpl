<div id="invite" class="generic-content-wrapper">
  <div class="section-title-wrapper app-content-header">
		<header class="entry__header">
			<h2 class="entry__title h1">{{$invite}}
		</h2>
	</header>
    <h4 class="zai_il">{{$lcclane}}</h4>
  </div>
  <div class="section-content-wrapper">

    <form action="invite" method="post" id="invite-form" >

      <input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

      <pre>{{$ihave}}<br>{{$wehave}}</pre>

      <div id="zai-re" style="visibility: hidden;">
        <div class="zai_h0 fa"></div>
        <pre id="zai-remsg"></pre>
      </div>

      <div id="invite-recipient-textarea" class="mb-3 field custom">

        <label for="zaito">{{$m11}}<sup class="zai_qmc">({{$n11}})</sup></label>
        <textarea id="zai-to" name="zaito" rows="6" class="form-control"></textarea>

        <span class="font-weight-bold">{{$m10}}<sup class="zai_qmc">({{$n10}})</sup></span>
        <a id="zai-ax" href="javascript:;" class="zai_ax zai_b">check</a><br>

        <hr>
        {{$inv_expire}}
      </div>

      <hr>

      <div class="">
        <div class="zai_h0">{{$subject_label}}
          <span id="zai-subject">{{$subject}}</span>
        </div>

        <div id="invite-message-textarea" class="mb-3 field custom">
          <label for="zaitxt">{{$m12}}<sup class="zai_qmc">({{$n12}})</sup></label>
          <textarea id="zai-txt" name="zaitxt" rows="6" class="form-control">{{$personal_message}}</textarea>
        </div>
      </div>

      <div class="zai_h0">{{$m13}}</div><sup class="zai_qmc">({{$n13}})</sup> {{$tplin}}<br>
      <pre id="zai-ims">
      {{$standard_message}}
      </pre>
      <pre id="zai-due">
      {{$due}}
      </pre>

      <div id="invite-submit-wrapper" class="mb-3">
        <button class="btn btn-primary btn-sm" type="submit" id="invite-submit" name="submit" value="{{$submit}}">{{$submit}}</button>
      </div>
      <input type='hidden' id="zai-reon" name='zaireon' value=''>
    </form>

  </div>
</div>

<script>
  // @hilmar |->
  typeof(window.tao) == 'undefined' ? window.tao = {} : '';
  tao.zai = { vsn: '2.0.0', s: {}, t: {} };
  {{$tao}}
  $('head').append(
  '<style> '+
  '  .zai_h0 { font-size: 1.2rem; display: inline; }'+
  '  .zai_hi { background: #ffc107; font-weight: bold; }'+
  '  .zai_bi { margin: 0 0.2em 0 1em; }'+
  '  .zai_lcc, .zai_qmc, .zuiqmid { font-family: monospace; text-transform: uppercase; }'+
  '  .zai_lcc5 { display: none; }'+
  '  .zai_ax { margin-inline: 8rem; }'+
  '  .zai_il { display: inline; }'+
  '  .zai_b  { font-weight: bold; }'+
  '  .zai_n  { width: 5em; text-align: center; }'+
  '  #id_zaiexpire_fs  { display: inline-block; }'+
  '  .invites { text-transform: capitalize; }'+
  '</style>');
  $('#zai-txt').attr('placeholder','{{$personal_pointer}}');
  zaitx();
  $('.zuiqmid').removeClass('required');
  $('#invite')
  .delegate('.invites', 'click', function() {
    tao.zai.itpl=$(this).text();
    $('.invites').removeClass('zai_hi');
    $('#zai-'+tao.zai.itpl).addClass('zai_hi');
    zaitx();
    })
  .delegate('.zai_lcc', 'click', function() {
    tao.zai.lcc=$(this).text();
    if ( $(this).hasClass('zai_lcc2') ) {
      tao.zai.lccg = '.zai_lccg' + tao.zai.lcc.substr(0,2);
      $('.zai_lcc5:not('+tao.zai.lccg+')').hide();
      if ( $(this).hasClass('zai_hi') ) {
        $('.zai_lcc5'+tao.zai.lccg).toggle();
      }
    }
    $('.zai_lcc').removeClass('zai_hi');
    $(this).addClass('zai_hi');
    $.each( tao.zai.t[tao.zai.lcc], function(k,v) {
      tao.zai.lccmy=tao.zai.lcc;
    });
    zaitx();
  });
  $('#zai-ax').click( function() {
    tao.zai.c2s={};
    tao.zai.c2s.to=$('#zai-to').val();
    if (tao.zai.c2s.to=='') { return false; };
    // tao.zai.c2s.lcc=$('.zai_lcc.zai_hi').text();
    $.ajax({
      type: 'POST', url: 'invite',
      data: {
        zaito: tao.zai.c2s.to,
        zailcc: tao.zai.lccmy,
        zaidurn: $('#zaiexpiren').val(),
        zaidurq: $('input[name="zaiexpire"]:checked').val(),
        form_security_token: $("input[name='form_security_token']").val()
      }
    }).done( function(r) {
      tao.zai.r = JSON.parse(r);
      $('#zai-re').attr('style', 'visibility: show;');
      $('#zai-remsg').text(tao.zai.r.feedbk);
      $('#zai-due').text(tao.zai.r.due);
    })
  });
  $('#invite-submit').click( function() {
    // $('#zai-txt').val($('#zai-ims').text());
    tao.zai.reon = {subject: $('#zai-subject').text(),
       lang: tao.zai.lccmy, tpl: tao.zai.itpl,
       whereami: tao.zai.whereami, whoami: tao.zai.whoami};
    $('#zai-reon').val(JSON.stringify(tao.zai.reon));
  });
  function zaitx() {
    typeof(tao.zai.s[tao.zai.lccmy]) !== 'undefined' && typeof(tao.zai.s[tao.zai.lccmy][tao.zai.itpl]) !== 'undefined'
    ? $('#zai-subject').text(decodeURIComponent(tao.zai.s[tao.zai.lccmy][tao.zai.itpl]))
    : $('#zai-subject').text('Invitation');
    typeof(tao.zai.t[tao.zai.lccmy]) !== 'undefined' && typeof(tao.zai.t[tao.zai.lccmy][tao.zai.itpl]) !== 'undefined'
    ? $('#zai-ims').text(decodeURIComponent(tao.zai.t[tao.zai.lccmy][tao.zai.itpl]))
    : $('#zai-ims').text(' ');
  }
  // @hilmar <-|
</script>
