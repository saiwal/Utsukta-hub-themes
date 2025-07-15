  <div class="card mb-3">
    <div class="row align-items-center flex-column flex-md-row card-body ">
      <!-- Profile Section (image + name) -->
      <div class="col-12 col-md-4 mb-3 mb-md-0">
        <div class="d-flex align-items-center">
          <a href="{{$contact.url}}"><img src="{{$contact.thumb}}" alt="{{$contact.name}}" class="rounded-circle me-3  img-size-32" alt="User"></a>
          <div class="flex-column">
            <h6 class="mb-0">{{include "connstatus.tpl" perminfo=$contact.perminfo}}{{if $contact.public_forum}}<i class="bi bi-chat" title="{{$group_label}}"></i>&nbsp;{{/if}}<a href="{{$contact.url}}" title="{{$contact.img_hover}}">{{$contact.name}}</a></h6>
            {{if $contact.phone}}&nbsp;<a class="btn btn-outline-secondary btn-sm" href="tel:{{$contact.phone}}" title="{{$contact.call}}"><i class=bi bi-phone connphone"></i></a>{{/if}}
			      {{if $contact.webbie}}
            <small class="text-muted text-break">{{$contact.webbie}}</small>
      			{{/if}}
          </div>
        </div>
      </div>
      <!-- Age of Account -->
      <div class="col-md-3 text-center text-md-start mt-2 mt-md-0">
			{{if $contact.connected}}
        <small></strong> <span class="autotime" title="{{$contact.connected}}"></span></small>
			{{/if}}
      </div>

      <!-- Account Type -->
      <div class="col-md-2 d-flex flex-column text-center text-md-start mt-2 mt-md-0">
			{{if $contact.network}}
        <small>{{$contact.network}}</small>
        <small><a href="{{$contact.recentlink}}" rel="nofollow noopener">{{$contact.recent_label}}</a></small>
			{{/if}}
      </div>

      <!-- Category -->
      <div class="col-md-2 text-center text-md-start mt-2 mt-md-0">
        {{if $contact.status}}
        	{{foreach $contact.states as $state}}
        		<span class="badge rounded-pill bg-danger text-white me-1" title="">{{$state}}</span>
          {{/foreach}}
    		{{/if}}
        <small><span id="contact-role-{{$contact.id}}" class="badge bg-warning text-dark me-1" title="{{$role_label}}">{{$contact.role}}</span></small>
      </div>

      <!-- Edit Icon -->
      <div class="col-md-1 text-end mt-2 mt-md-0">

        <button type="button" class="btn btn-tool contact-edit" title="{{$contact.edit_hover}}" data-id="{{$contact.id}}">
	  			<i class="bi bi-pencil contact-edit-icon-{{$contact.id}}"></i>
  			</button>
      </div>
    </div>
  </div>

