<!-- File inputs (unchanged but improved with Bootstrap classes) -->
<input id="invisible-wall-file-upload" type="file" name="files" class="d-none" multiple>
<input id="invisible-comment-upload" type="file" name="files" class="d-none" multiple>

<form id="profile-jot-form" action="{{$action}}" method="post" class="acl-form" data-form_id="profile-jot-form"
  data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}'
  data-deny_gid='{{$deny_gid}}' data-bang='{{$bang}}'>
  
  <!-- Hidden inputs and other form elements... -->

  <div class="mb-4 card border-0 shadow-sm" id="profile-jot-wrapper">
    <div class="card-body p-4">
      <!-- Category Section -->
      {{if $catsenabled}}
      <div id="jot-category-wrap" class="jothidden mb-3">
        <label class="form-label small text-muted mb-1">{{$placeholdercategory}}</label>
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0">
            <i class="bi bi-tag"></i>
          </span>
          <input name="category" id="jot-category" type="text" 
                 class="form-control tagsinput" 
                 value="{{$category}}"
                 data-role="tagsinput"
                 data-tag-class="badge rounded-pill bg-primary"
                 data-separator=","
                 placeholder="Add a category...">
        </div>
        <div class="form-text small text-muted">Separate categories with commas or press Enter</div>
      </div>
      {{/if}}

      <!-- Rest of the form elements... -->

      <!-- Submit Section -->
      <div id="profile-jot-submit-wrapper" class="d-flex justify-content-between align-items-center mt-4">
        <div class="d-flex gap-2">
          <!-- Privacy controls... -->
        </div>
        <div class="d-flex gap-2">
          <!-- Submit button... -->
        </div>
      </div>
    </div>
  </div>
</form>

<!-- Required CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.css">

<!-- Required JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.min.js"></script>

<!-- Custom Styling for Tagsinput -->
<style>
  .bootstrap-tagsinput {
    width: 100%;
    border: 0;
    box-shadow: none !important;
    padding: 0.375rem 0;
  }
  
  .bootstrap-tagsinput .badge {
    padding: 0.5em 0.75em;
    margin: 2px 3px;
    font-weight: 500;
  }
  
  .bootstrap-tagsinput input {
    color: inherit;
    background: transparent;
    border: none !important;
    box-shadow: none !important;
    margin-left: 4px !important;
  }
  
  .bootstrap-tagsinput input:focus {
    outline: none !important;
  }
</style>

<!-- Initialize Tagsinput -->
<script>
  $(document).ready(function() {
    $('#jot-category').tagsinput({
      trimValue: true,
      confirmKeys: [13, 44], // Enter and comma
      tagClass: function(item) {
        return 'badge rounded-pill bg-primary';
      }
    });

    // Fix Bootstrap 5 conflict
    $.fn.tagsinput.Constructor.prototype.$input = function() {
      return this.$input.find('input');
    };
  });
</script>
