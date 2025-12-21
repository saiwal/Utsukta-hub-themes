<script>
	function confirm_delete(uname){
		return confirm( "{{$confirm_delete}}".format(uname));
	}
	function confirm_delete_multi(){
		return confirm("{{$confirm_delete_multi}}");
	}
	function selectall(cls){
		$("."+cls).attr('checked','checked');
		return false;
	}
</script>
<div class="generic-content-wrapper-styled table-responsive" id='adminpage'>
	<div class="section-title-wrapper app-content-header">
	<h3 class="border-0">{{$title}}</h3>
	</div>
  <div class="mb-4">
    <div class="h4">
      {{$page}}
    </div>
    <div class="table-responsive">
	<form action="{{$baseurl}}/admin/channels" method="post">
        <input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

		{{if $channels}}
			<table id="channels" class="table table-hover">
				<thead>
				<tr>
					{{foreach $th_channels as $th}}<th><a href="{{$base}}&key={{$th.1}}&dir={{$odir}}">{{$th.0}}</a></th>{{/foreach}}
					<th></th>
					<th></th>
					<th></th>
					<th></th>
				</tr>
				</thead>
				<tbody>
				{{foreach $channels as $c}}
					<tr>
						<td class='channel_id'>{{$c.channel_id}}</td>
						<td class='channel_name'><a href="channel/{{$c.channel_address}}">{{$c.channel_name}}</a></td>
						<td class='channel_address'>{{$c.channel_address}}</td>
						<td class="checkbox_bulkedit"><input type="checkbox" class="channels_ckbx p-1" id="id_channel_{{$c.channel_id}}" name="channel[]" value="{{$c.channel_id}}"/></td>
						<td class="tools">
							<a href="{{$baseurl}}/admin/channels/block/{{$c.channel_id}}?t={{$form_security_token}}" class="p-1 text-reset" title='{{if ($c.blocked)}}{{$unblock}}{{else}}{{$block}}{{/if}}'><i class='bi bi-ban admin-icons {{if ($c.blocked)}}text-danger{{/if}}'></i></a>
						</td>
						<td class="tools">
							<a href="{{$baseurl}}/admin/channels/code/{{$c.channel_id}}?t={{$form_security_token}}" class="p-1 text-reset" title='{{if ($c.allowcode)}}{{$uncode}}{{else}}{{$code}}{{/if}}'><i class='bi bi-code admin-icons {{if ($c.allowcode)}}text-danger{{/if}}'></i></a>
						</td>
						<td class="tools">
							<a href="{{$baseurl}}/admin/channels/delete/{{$c.channel_id}}?t={{$form_security_token}}" class="p-1 text-reset" title='{{$delete}}' onclick="return confirm_delete('{{$c.channel_name}}')"><i class='bi bi-trash admin-icons'></i></a>
						</td>
					</tr>
				{{/foreach}}
				</tbody>
			</table>
			<div class='selectall'><a href='#' onclick="return selectall('channels_ckbx');">{{$select_all}}</a></div>
			<div class="submit">
                <input type="submit" name="page_channels_block" class="btn btn-primary" value="{{$block}}/{{$unblock}}" />
                <input type="submit" name="page_channels_code" class="btn btn-primary" value="{{$code}}/{{$uncode}}" />
                <input type="submit" name="page_channels_delete" class="btn btn-primary" onclick="return confirm_delete_multi()" value="{{$delete}}" />
            </div>
		{{else}}
			NO CHANNELS?!?
		{{/if}}
	</form>
</div>
</div>
</div>
