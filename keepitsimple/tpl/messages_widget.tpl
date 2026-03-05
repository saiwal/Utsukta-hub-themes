<ul class="nav nav-underline d-flex justify-content-between h5">
  <li class="nav-item">
    <a class="nav-link active messages-type" href="#" title="{{$strings.messages_title}}" data-messages_type="">
      <i class="bi bi-chat generic-icons"></i>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link messages-type" href="#" title="{{$strings.direct_messages_title}}" data-messages_type="direct">
      <i class="bi bi-envelope generic-icons"></i>
    </a>
  </li>
  {{if $feature_star}}
  <li class="nav-item">
    <a class="nav-link messages-type" href="#" title="{{$strings.starred_messages_title}}" data-messages_type="starred">
      <i class="bi bi-star generic-icons"></i>
    </a>
  </li>
  {{/if}}
  {{if $feature_file}}
  <li class="nav-item">
    <a class="nav-link messages-type" href="#" title="{{$strings.filed_messages_title}}" data-messages_type="filed">
      <i class="bi bi-folder generic-icons"></i>
    </a>
  </li>
  {{/if}}
  <li class="nav-item">
    <a class="nav-link messages-type" href="#" title="{{$strings.notice_messages_title}}"
      data-messages_type="notification">
      <i class="bi bi-exclamation-circle generic-icons"></i>
    </a>
  </li>
</ul>
<div id="messages-widget" class="overflow-auto clearfix card border-0 mb-5 " style="height: 70vh;">
  <div id="messages-template" rel="template" class="d-none">
    <a href="{6}" class="list-group-item list-group-item-action message" data-b64mid="{0}">
      <div class="mb-2 align-middle">
        <img src="{9}" loading="lazy" class="rounded float-start me-2 menu-img-2 shadow">
        <div class="text-nowrap">
          <div class="d-flex justify-content-between align-items-center lh-sm">
            <div class="text-truncate">
              {7}
              <strong title="{4}">{4}</strong>
            </div>
            <small class="autotime-narrow opacity-75" title="{1}"></small>
          </div>
          <div class="text-truncate">
            <small class="opacity-75" title="{5}">{5}</small>
          </div>
        </div>
      </div>
      <div class="mb-2">
        <div class="text-break">{2}</div>
      </div>
      <small class="opacity-75">{3}</small>
      {8}
    </a>
  </div>
  <div id="messages-container" class="list-group list-group-flush" data-offset="10">

    <div id="messages-author-container" class="list-group-item notifications-textinput">
      <!-- <div class="text-muted notifications-textinput-filter"><i class="bi bi-filter"></i></div> -->
      <input id="messages-author" type="text" class="h-full-width mb-2" placeholder="{{$strings.filter}}">
      <div id="messages-author-input-clear" class="text-muted notifications-textinput-clear d-none"><i
          class="bi bi-x-lg"></i></div>
    </div>
    {{if $feature_file}}
    <div id="messages-file-container" class="list-group-item notifications-textinput d-none">
      <div class="ss-custom-select">
        <select id="messages-file" class="h-full-width">
          <option value="">{{$strings.file_filter}}</option>
          {{foreach $file_tags as $opt=>$val}}
          <option value="{{$val}}">{{$val}}</option>
          {{/foreach}}
        </select>
        <button class="btn btn-outline-secondary d-none" type="button" id="messages-file-input-clear">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
    {{/if}}
    {{foreach $entries as $e}}
    <a href="{{$e.href}}" class="list-group-item list-group-item-action message" data-b64mid="{{$e.b64mid}}">
      <div class="mb-2 align-middle">
        <img src="{{$e.author_img}}" loading="lazy" class="rounded float-start me-2 menu-img-2 ">
        <div class="text-nowrap">
          <div class="d-flex justify-content-between align-items-center lh-sm">
            <div class="text-truncate pe-1">
              {{$e.icon}}
              <strong title="{{$e.author_name}}">{{$e.author_name}}</strong>
            </div>
            <small class="autotime-narrow opacity-75" title="{{$e.created}}"></small>
          </div>
          <div class="text-truncate">
            <small class="opacity-75" title="{{$e.author_addr}}">{{$e.author_addr}}</small>
          </div>
        </div>
      </div>
      <div class="mb-2">
        <div class="text-break">{{$e.summary}}</div>
      </div>
      <small class="opacity-75">{{$e.info}}</small>
      {{if $e.unseen_count}}
      <span
        class="badge bg-{{$e.unseen_class}} border border-{{$e.unseen_class}} border-1 text-body-{{$e.unseen_class}} rounded-pill position-absolute bottom-0 end-0 m-2 unseen_count"
        title="{{$strings.unseen_count}}">{{$e.unseen_count}}</span>
      {{/if}}
    </a>
    {{/foreach}}
    <div id="messages-empty" class="list-group-item border-0" {{if $entries}} style="display: none;" {{/if}}>
      {{$strings.empty}}...
    </div>
    <div id="messages-loading" class="list-group-item" style="display: none;">
      {{$strings.loading}}<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span
          class="dot-3">.</span></span>
    </div>
  </div>
</div>
<script>
  let messages_offset = {{$offset}};
  let get_messages_page_active = false;
  let messages_type;
  let author_hash;
  let author_url;
  let author;
  let file;

  let unseen_filter = '';
  function apply_unseen_filter() {
    $('#messages-container .message').each(function () {
      const badge = $(this).find('.unseen_count');
      const hasBadge = badge.length > 0;
      const badgeText = badge.text().trim();

      if (unseen_filter === 'new') {
        // badge exists but is empty/whitespace
        if (!hasBadge || badgeText !== '') {
          $(this).hide();
        } else {
          $(this).show();
        }
      }
      else if (unseen_filter === 'active') {
        // badge exists with a number
        if (!hasBadge || badgeText === '') {
          $(this).hide();
        } else {
          $(this).show();
        }
      }
      else {
        $(this).show();
      }
    });
  }
  $(document).ready(function () {
    updateRelativeTime('.autotime-narrow');

    $(document).on('change', 'input[name="messages-unseen-filter"]', function (e) {
      unseen_filter = e.target.value;
      apply_unseen_filter();
    });

    if (bParam_mid) {
      $('.message[data-b64mid=\'' + bParam_mid + '\']').addClass('active');
    }

    $("#messages-author").name_autocomplete(baseurl + '/acl', 'a', false, function (data) {
      // a workaround to not re-trigger autocomplete after initial click
      $("#messages-author").val('').attr('placeholder', data.name);
      $('#textcomplete-dropdown').hide();

      $('#messages-container .message').remove();
      $('#messages-author-container').addClass('active sticky-top');
      $('#messages-author-input-clear').removeClass('d-none');

      author_hash = data.xid;
      author_url = data.url;
      author = messages_type === 'notification' ? author_url : author_hash;
      messages_offset = 0;
      get_messages_page();
    });

    $('#messages-file').on('change', function (e) {
      file = e.currentTarget.value;

      // Reset if "All" (empty value) is chosen
      if (!file) {
        $('#messages-file-container').removeClass('active sticky-top');
        $('#messages-file-input-clear').addClass('d-none');
        $('#messages-container .message').remove();
        messages_offset = 0;
        get_messages_page();
        return;
      }

      // Otherwise apply folder filter
      $('#messages-container .message').remove();
      $('#messages-file-container').addClass('active sticky-top');
      $('#messages-file-input-clear').removeClass('d-none');

      messages_offset = 0;
      get_messages_page();
    });
    $(document).on('click', '#messages-author-input-clear, #messages-file-input-clear', function () {
      $('#messages-author, #messages-file').val('');
      $("#messages-author").attr('placeholder', '{{$strings.filter}}');
      $("#messages-file").attr('placeholder', '{{$strings.file_filter}}');

      $('#messages-author-container').removeClass('active sticky-top');
      $('#messages-file-container').removeClass('active sticky-top');
      $('#messages-author-input-clear').addClass('d-none');
      $('#messages-container .message').remove();
      author = '';
      file = '';
      author_hash = '';
      author_url = '';
      messages_offset = 0;
      get_messages_page();
    });

  });

// Close offcanvas when a message is clicked (mobile only)
$(document).on('click', '#messages-container .message', function (e) {
    // Only trigger if viewport is smaller than large (lg)
    if (window.innerWidth < 992) {
        const offcanvasEl = document.getElementById('offcanvasResponsive');
        const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl) || new bootstrap.Offcanvas(offcanvasEl);
        bsOffcanvas.hide();
    }
});
  $('#messages-widget').on('scroll', function () {
    if (this.scrollTop > this.scrollHeight - this.clientHeight - (this.scrollHeight / 7)) {
      get_messages_page();
    }
  });

$(document).on('click', '.messages-type', function (e) {
    e.preventDefault();
    $('.messages-type').removeClass('active');
    $(this).addClass('active');

    messages_offset = 0;
    messages_type = $(this).data('messages_type');
    author = messages_type === 'notification' ? author_url : author_hash;

    // Reset unseen filter toggle to "All"
    $('#filter-all').prop('checked', true);
    unseen_filter = '';

    // Show/hide toolbar based on tab
    if (!messages_type) {
        // empty string = Conversations / All messages
        $('#messages-filter-toolbar').removeClass('d-none');
    } else {
        $('#messages-filter-toolbar').addClass('d-none');
    }

    if (messages_type === 'filed') {
        $('#messages-author-container').addClass('d-none');
        $('#messages-file-container').removeClass('d-none');
    } else {
        $('#messages-author-container').removeClass('d-none');
        $('#messages-file-container').addClass('d-none');
    }

    $('#messages-container .message').remove();
    get_messages_page();
});
  function get_messages_page() {

    if (get_messages_page_active)
      return;

    if (messages_offset === -1)
      return;

    get_messages_page_active = true;
    $('#messages-loading').show();
    $('#messages-empty').hide();

    $.ajax({
      type: 'post',
      url: 'hq',
      data: {
        offset: messages_offset,
        type: messages_type,
        author: author,
        file: file
      }
    }).done(function (obj) {
      get_messages_page_active = false;
      messages_offset = obj.offset;
      let html;
      let tpl = $('#messages-template[rel=template]').html();
      if (obj.entries.length) {
        obj.entries.forEach(function (e) {
          html = tpl.format(
            e.b64mid,
            e.created,
            e.summary,
            e.info,
            e.author_name,
            e.author_addr,
            e.href,
            e.icon,
            e.unseen_count ? '<span class="badge bg-' + e.unseen_class + ' border border-primary border-1' + ' text-body-' + e.unseen_class + '-emphasis rounded-pill position-absolute bottom-0 end-0 m-2 unseen_count" title="{{$strings.unseen_count}}">' + e.unseen_count + '</span>' : '',
            e.author_img
          );
          $('#messages-loading').before(html);
        });
      }
      else {
        $('#messages-empty').show();
      }
      if (bParam_mid) {
        $('.message[data-b64mid=\'' + bParam_mid + '\']').addClass('active');
      }
      $('#messages-loading').hide();
      updateRelativeTime('.autotime-narrow');
      apply_unseen_filter();
    });

  }
</script>
