<div id="help-content" class="generic-content-wrapper">
	<div class="clearfix section-title-wrapper app-content-header">
		<div class="h3 mt-0">{{$module->get_page_title()}}</div>
	</div>
	{{if $module->missing_translation()}}
	<div class="notice section-content-info-wrapper">
		{{$module->missing_translation_message()}}
	</div>
	{{/if}}
	<div class="section-content-wrapper-np">
    <div class="card mb-3 border-0">
      <div class="card-body">
		<details id="doco-top-toc-wrapper" class="alert alert-info position-absolute top-0 end-0 m-2 p-2">
			<summary id="doco-top-toc-heading">{{$module->get_toc_heading()}}</summary>
			<ul id="doco-top-toc"></ul>
		</details>
		<div id="doco-content">
			{{$module->render_content()}}
		</div>
		</div>
		</div>
	</div>
</div>
