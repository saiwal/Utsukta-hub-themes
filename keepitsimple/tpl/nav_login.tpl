{{if $nav.login && !$userinfo}}
<div id="nav-login" class="modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title m-2">{{$nav.loginmenu.1.1}}</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-4">
					{{$nav.login}}
			</div>
		</div>
	</div>
</div>
{{/if}}
