<div class="mb-3">
  <div class="h5">
    {{$contacts}}
  </div>
  {{if $micropro}}
  <!-- /.card-header -->
  <div class="p-1 overflow-y-auto" style="max-height: 19rem;">
    <div class="row row-cols-5 g-2">
      {{foreach $micropro as $m}}
        {{$m}}
      {{/foreach}}
    </div>
    <!-- /.users-list -->
  </div>
  {{/if}}
  <!-- /.card-body -->
  {{if $viewconnections}}
  <div class="text-center">
    <a href="viewconnections/{{$nickname}}"
       class="link-primary link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">{{$viewconnections}}</a>
  </div>
  <!-- /.card-footer -->
  {{/if}}
</div>
