<label for="id_{{$field.0}}" class="d-flex justify-space-between">{{$field.1}}
  <label class="switchlabel" for="id_{{$field.0}}">
    <span class="onoffswitch-inner" data-on="{{if $field.5}}{{$field.5.1}}{{/if}}"
      data-off="{{if $field.5}}{{$field.5.0}}{{/if}}">
    </span>
  </label>  <input type="checkbox" class="the-end" name="{{$field.0}}" id="id_{{$field.0}}" value="{{$field.3}}" {{if $field.2}}checked="checked"
    {{/if}}>

</label><br>
