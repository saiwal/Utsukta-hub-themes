<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<div class="section-content-wrapper clearfix row row-cols-2 row-cols-sm-3 row-cols-md-3 g-3">
		{{foreach $contacts as $contact}}
		{{include file="contact_template.tpl"}}
		{{/foreach}}
		{{** make sure this element is at the bottom - we rely on that in endless scroll **}}
		<div id="page-end" class="float-start w-100"></div>
	</div>
</div>
<script>$(document).ready(function() { loadingPage = false;});</script>
<div id="page-spinner" class="spinner-wrapper">
	<div class="spinner m"></div>
</div>
