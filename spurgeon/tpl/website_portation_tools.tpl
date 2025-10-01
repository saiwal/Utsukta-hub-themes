<div id="website-portation-tools">
  <div class="card collapsed-card mb-3">
    <div class="card-header">
      {{$import_label}}
    </div>
    <div class="nav nav-pills flex-column card-body">
      <div id="import-form" class="sub-menu-wrapper">
        <div class="sub-menu">
          <form enctype="multipart/form-data" method="post" action="">
            <input type="hidden" name="action" value="scan">
            <p class="descriptive-text">{{$file_import_text}}</p>
            <div class="mb-3">
              <input class="form-control" type="text" name="path" title="{{$hint}}" placeholder="{{$desc}}" />
            </div>
            <div class="mb-3">
              <button class="btn btn-primary btn-sm" type="submit" name="cloudsubmit" value="{{$select}}">Submit</button>
            </div>
            <!-- Or upload a zipped file containing the website -->
            <p class="descriptive-text">{{$file_upload_text}}</p>
            <div class="mb-3">
              <input class="form-control-file w-100" type="file" name="zip_file" />
            </div>
            <div class="mb-3">
              <button class="btn btn-primary btn-sm" type="submit" name="w_upload" value="w_upload">Submit</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <div class="card collapsed-card mb-3">
    <div class="card-header">
      {{$export_label}}
    </div>
  	<div class="nav nav-pills flex-column card-body">
      <div id="export-form" class="sub-menu-wrapper">
        <div class="sub-menu">
          <form enctype="multipart/form-data" method="post" action="">
            <input type="hidden" name="action" value="exportzipfile">
            <!-- Or download a zipped file containing the website -->
            <p class="descriptive-text">{{$file_download_text}}</p>
            <div class="mb-3">
              <input class="form-control" type="text" name="zipfilename" title="{{$filename_hint}}" placeholder="{{$filename_desc}}" value="" />
            </div>
            <div class="mb-3">
              <button class="btn btn-primary btn-sm" type="submit" name="w_download" value="w_download">Submit</button>
            </div>
          </form>
        </div>
      </div>
  		<div id="export-cloud-form" class="sub-menu-wrapper">
	  		<div class="sub-menu">
		  		<form enctype="multipart/form-data" method="post" action="">
			  		<input type="hidden" name="action" value="exportcloud">
				  	<!-- Or export the website elements to a cloud files folder -->
  					<p style="margin-top: 10px;" class="descriptive-text">{{$cloud_export_text}}</p>
	  				<div class="mb-3">
		  				<input class="form-control" type="text" name="exportcloudpath" title="{{$cloud_export_hint}}" placeholder="{{$cloud_export_desc}}" />
			  		</div>
				  	<div class="mb-3">
					  	<button class="btn btn-primary btn-sm" type="submit" name="exportcloudsubmit" value="{{$cloud_export_select}}">Submit</button>
  					</div>
	  			</form>
		  	</div>
  		</div>
  	</div>
  </div>
</div>
