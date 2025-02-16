<div class="generic-content-wrapper-styled table-responsive" id="adminpage">
	<div class="section-title-wrapper app-content-header">
	<h3>{{$title}} - {{$page}}</h3>
	</div>

	<form action="{{$baseurl}}/admin/accounts" method="post">
		<input type="hidden" name="form_security_token" value="{{$form_security_token}}">

		<h3>{{$h_pending}}</h3>
		{{if $debug}}<div>{{$debug}}</div>{{/if}}
		{{if $pending}}
		<table id="pending" class="table table-hover">
			<thead>
			<tr>
				{{foreach $th_pending as $th}}<th>{{$th}}</th>{{/foreach}}
				<th></th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			{{foreach $pending as $n => $u}}
			<tr title="{{$u.status.0}}" class="{{$u.status.1}}">
				<td class="text-nowrap">{{$u.reg_created}}</td>
				<td class="text-nowrap">{{$u.reg_did2}}</td>
				<td class="text-break">{{$u.reg_email}}</td>
				<td class="">{{$u.reg_atip}}</td>
				<td class="">{{$u.reg_atip_n}}</td>
				<td class="checkbox_bulkedit"><input type="checkbox" class="pending_ckbx" id="id_pending_{{$n}}" name="pending[]" value="{{$n}}"></td>
				<td class="tools">
					<a id="zara_{{$n}}" {{* href="{{$baseurl}}/regmod/allow/{{$n}}" *}} class="zar2s zara btn btn-default btn-xs" title="{{$approve}}"><i class="bi bi-hand-thumbs-up generic-icons"></i></a>
					<a id="zard_{{$n}}" {{* href="{{$baseurl}}/regmod/deny/{{$n}}" *}} class="zar2s zard btn btn-default btn-xs" title="{{$deny}}"><i class="bi bi-hand-thumbs-down generic-icons"></i></a>
					<span id="zarreax_{{$n}}" class="zarreax"></span>
				</td>
			</tr>
			<tr title="{{$u.status.0}}" class="{{$u.status.1}}">
				<td colspan="7"><strong>{{$msg}}:</strong> {{$u.msg}}</td>
			</tr>
			{{/foreach}}
			</tbody>
		</table>
		<div class="float-end">
			<a id="zar2sat" class="btn btn-sm btn-primary" href="javascript:;">{{$sel_tall}}</a>
			<a id="zar2aas" class="zar2xas btn btn-sm btn-success" href="javascript:;"><i class="bi bi-check-lg"></i> {{$sel_aprv}}</a>
			<a id="zar2das" class="zar2xas btn btn-sm btn-danger" href="javascript:;"><i class="bi bi-x-lg"></i> {{$sel_deny}}</a>
		</div>
		{{else}}
		<div class="text-muted">
		{{$no_pending}}
		</div>
		{{/if}}
		<div class="float-start">
			<a class="btn btn-sm btn-link" href="{{$get_all_link}}">{{$get_all}}</a>
		</div>
		<div class="clearfix"></div>
		<br><br>
		<h3>{{$h_users}}</h3>
		{{if $users}}
			<table id="users" class="table table-hover">
				<thead>
				<tr>
					{{foreach $th_users as $th}}<th><a href="{{$base}}&key={{$th.1}}&dir={{$odir}}">{{$th.0}}</a></th>{{/foreach}}
					<th></th>
					<th></th>
					<th></th>
				</tr>
				</thead>
				<tbody>
				{{foreach $users as $u}}
					<tr>
						<td class="account_id">{{$u.account_id}}</td>
						<td class="email">{{if $u.blocked}}
							<a href="admin/account_edit/{{$u.account_id}}"><i>{{$u.account_email}}</i></a>
						{{else}}
							<a href="admin/account_edit/{{$u.account_id}}"><strong>{{$u.account_email}}</strong></a>
						{{/if}}</td>
						<td class="channels">{{$u.channels}}</td>
						<td class="register_date">{{$u.account_created}}</td>
						<td class="login_date">{{$u.account_lastlog}}</td>
						<td class="account_expires">{{$u.account_expires}}</td>
						<td class="service_class">{{$u.account_service_class}}</td>
						<td class="checkbox_bulkedit"><input type="checkbox" class="users_ckbx p-1" id="id_user_{{$u.account_id}}" name="user[]" value="{{$u.account_id}}"><input type="hidden" name="blocked[]" value="{{$u.blocked}}"></td>
						<td class="tools">
							<a href="{{$baseurl}}/admin/accounts/delete/{{$u.account_id}}?t={{$form_security_token}}" class="p-1 text-reset" title='{{$delete}}' onclick="return confirm_delete('{{$u.name}}')"><i class="bi bi-trash admin-icons"></i></a>
						</td>
						<td class="tools">
							<a href="{{$baseurl}}/admin/accounts/{{if ($u.blocked)}}un{{/if}}block/{{$u.account_id}}?t={{$form_security_token}}" class="p-1 text-reset" title='{{if ($u.blocked)}}{{$unblock}}{{else}}{{$block}}{{/if}}'><i class="bi fa-ban admin-icons{{if ($u.blocked)}} dim{{/if}}"></i></a>
						</td>
					</tr>
				{{/foreach}}
				</tbody>
			</table>

			<div class="selectall"><a id="zarckbxtoggle" href="javascript:;">{{$select_all}}</a></div>
		{{*
			<div class="selectall"><a href="#" onclick="return toggle_selectall('users_ckbx');">{{$select_all}}</a></div>
		*}}
			<div class="submit">
                <input type="submit" name="page_accounts_block" class="btn btn-primary" value="{{$block}}/{{$unblock}}" />
                <input type="submit" name="page_accounts_delete" class="btn btn-primary" onclick="return confirm_delete_multi()" value="{{$delete}}" />
            </div>
		{{else}}
			NO USERS?!?
		{{/if}}
	</form>
</div>
{{*
	COMMENTS for this template:
	hilmar, 2020.01
	script placed at the end
*}}
<script>
  	function confirm_delete(uname){
		return confirm( "{{$confirm_delete}}".format(uname));
	}
	function confirm_delete_multi(){
		return confirm("{{$confirm_delete_multi}}");
	}
	function toggle_selectall(cls){
		$("."+cls).prop("checked", !$("."+cls).prop("checked"));
		return false;
	}
  	// @hilmar |->
  	typeof(window.tao) == 'undefined' ? window.tao = {} : '';
  	tao.zar = { vsn: '2.0.0', c2s: {}, t: {} };
  	{{$tao}}
  	$('#adminpage').on( 'click', '#zar2sat', function() {
		$('input.pending_ckbx:checkbox').each( function() { this.checked = ! this.checked; });
  	});
  	$('#adminpage').on( 'click', '.zar2xas', function() {
  	 	tao.zar.c2s.x = $(this).attr('id').substr(4,1);
		$('input.pending_ckbx:checkbox:checked').each( function() {
			//if (this.checked)
			// take the underscore with to prevent numeric 0 headdage
			tao.zar.c2s.n = $(this).attr('id').substr(10);
    		$('#zarreax'+tao.zar.c2s.n).html(tao.zar.zarax);
    		zarCSC();
		});
  	});
  	$('.zar2s').click( function() {
    	tao.zar.c2s.ix=$(this).attr('id');
    	if (tao.zar.c2s.ix=='') { return false; };
    	tao.zar.c2s.n=tao.zar.c2s.ix.substr(4);
    	tao.zar.c2s.x=tao.zar.c2s.ix.substr(3,1);
    	$('#zarreax'+tao.zar.c2s.n).html(tao.zar.zarax);
    	zarCSC();
  	});

  	function zarCSC() {
  		$.ajax({
      		type: 'POST', url: 'admin/accounts',
      		data: {
        		zarat: tao.zar.c2s.n,
        		zardo: tao.zar.c2s.x,
        		zarse: tao.zar.zarar[(tao.zar.c2s.n).substr(1)],
        		form_security_token: $("input[name='form_security_token']").val()
      		}
    	}).done( function(r) {
      		tao.zar.r = JSON.parse(r);
      		$('#zarreax'+tao.zar.r.at).html(tao.zar.r.re + ',' + tao.zar.r.rc);
      		$('#zara'+tao.zar.r.at+',#zard'+tao.zar.r.at+',#id_pending'+tao.zar.r.at).remove();
      		//$('#zar-remsg').text(tao.zar.r.feedbk);
    	})
  	}

</script>
