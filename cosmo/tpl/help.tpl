<div id="help-content" class="generic-content-wrapper">
	<div class="clearfix section-title-wrapper app-content-header">
		<h3>{{$module->get_page_title()}}</h3>
	</div>
	{{if $module->missing_translation()}}
	<div class="notice section-content-info-wrapper">
		{{$module->missing_translation_message()}}
	</div>
	{{/if}}
	<div class="section-content-wrapper">
		<details id="doco-top-toc-wrapper">
			<summary id="doco-top-toc-heading">{{$module->get_toc_heading()}}</summary>
			<ul id="doco-top-toc"></ul>
		</details>
		<div id="doco-content">
			{{$module->render_content()}}
		</div>
	</div>
</div>
