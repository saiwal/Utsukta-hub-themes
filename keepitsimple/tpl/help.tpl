<div id="help-content" class="generic-content-wrapper">
	<div class="clearfix section-title-wrapper app-content-header">
		<header class="entry__header">
			<h2 class="entry__title h1">{{$module->get_page_title()}}
		</h2>
	</header>
	</div>
	{{if $module->missing_translation()}}
	<div class="notice section-content-info-wrapper">
		{{$module->missing_translation_message()}}
	</div>
	{{/if}}
	<div class="section-content-wrapper-np">
    <div class="mb-3 border-0">
		<details id="doco-top-toc-wrapper" class="alert osition-absolute top-0 end-0 m-2 p-2">
			<summary id="doco-top-toc-heading">{{$module->get_toc_heading()}}</summary>
			<ul id="doco-top-toc" style="list-style: none;"></ul>
		</details>
		<div id="doco-content">
			{{$module->render_content()}}
		</div>
		</div>
	</div>
</div>
