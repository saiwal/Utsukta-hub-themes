<div class="modal" id="item-filer-dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">{{$title}}</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
        </button>
      </div>
      <div class="modal-body">
        {{include file="field_combobox.tpl"}}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{$cancel}}</button>
        <button id="filer_save" type="button" class="btn btn-primary">{{$submit}}</button>
      </div>
    </div>
  </div>
</div>
