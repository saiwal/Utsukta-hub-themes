<div class="generic-content-wrapper">
  <div class="section-title-wrapper app-content-header">		<header class="entry__header">
			<h2 class="entry__title h1">{{$title}} - {{$page}}
			</h2>
		</header>
  </div>

    <div class="card-header">
      <!-- Static A–Z index -->
      <nav id="azIndex" class="small mt-2 user-select-none" aria-label="Alphabetical index">
        <span class="me-2 text-muted">Jump:</span>
        <a data-letter="A" class="me-1 text-muted disabled">A</a>
        <a data-letter="B" class="me-1 text-muted disabled">B</a>
        <a data-letter="C" class="me-1 text-muted disabled">C</a>
        <a data-letter="D" class="me-1 text-muted disabled">D</a>
        <a data-letter="E" class="me-1 text-muted disabled">E</a>
        <a data-letter="F" class="me-1 text-muted disabled">F</a>
        <a data-letter="G" class="me-1 text-muted disabled">G</a>
        <a data-letter="H" class="me-1 text-muted disabled">H</a>
        <a data-letter="I" class="me-1 text-muted disabled">I</a>
        <a data-letter="J" class="me-1 text-muted disabled">J</a>
        <a data-letter="K" class="me-1 text-muted disabled">K</a>
        <a data-letter="L" class="me-1 text-muted disabled">L</a>
        <a data-letter="M" class="me-1 text-muted disabled">M</a>
        <a data-letter="N" class="me-1 text-muted disabled">N</a>
        <a data-letter="O" class="me-1 text-muted disabled">O</a>
        <a data-letter="P" class="me-1 text-muted disabled">P</a>
        <a data-letter="Q" class="me-1 text-muted disabled">Q</a>
        <a data-letter="R" class="me-1 text-muted disabled">R</a>
        <a data-letter="S" class="me-1 text-muted disabled">S</a>
        <a data-letter="T" class="me-1 text-muted disabled">T</a>
        <a data-letter="U" class="me-1 text-muted disabled">U</a>
        <a data-letter="V" class="me-1 text-muted disabled">V</a>
        <a data-letter="W" class="me-1 text-muted disabled">W</a>
        <a data-letter="X" class="me-1 text-muted disabled">X</a>
        <a data-letter="Y" class="me-1 text-muted disabled">Y</a>
        <a data-letter="Z" class="me-1 text-muted disabled">Z</a>
        <a data-letter="#" class="ms-2 text-muted disabled">#</a>
      </nav>
    </div>

    <div class="card-body p-0">
      <div class="list-group list-group-flush" id="pluginList">
        {{foreach $plugins as $p}}
        <div class="list-group-item d-flex justify-content-between align-items-start plugin-item"
          data-name="{{$p.2.name|escape:'html'}}">
          <div class="me-auto">
            <h6 class="mb-1">
              <a href="{{$baseurl}}/admin/{{$function}}/{{$p.0}}">{{$p.2.name}}</a>
              <small class="text-muted">v{{$p.2.version}}</small>
              {{if $p.2.disabled}} <span class="badge bg-secondary">{{$disabled}}</span>{{/if}}
              {{if $p.2.experimental}} <span class="badge bg-warning text-dark">{{$experimental}}</span>{{/if}}
              {{if $p.2.unsupported}} <span class="badge bg-danger">{{$unsupported}}</span>{{/if}}
            </h6>
            <p class="text-body-secondary mb-1">{{$p.2.description}}</p>
          </div>
          <div>
            {{if ! $p.2.disabled}}
            <a class="toggleplugin"
              href="{{$baseurl}}/admin/{{$function}}/{{$p.0}}?a=t&amp;t={{$form_security_token}}"
              title="{{if $p.1==on}}Disable{{else}}Enable{{/if}}">
              <i class="bi {{if $p.1==on}}bi-check-square{{else}}bi-square{{/if}}"></i>
            </a>
            {{else}}
            <span class="btn btn-sm btn-outline-secondary disabled">
              <i class="bi fa-stop"></i>
            </span>
            {{/if}}
          </div>
        </div>
        {{/foreach}}
      </div>
    </div>

  <style>
    #azIndex a.disabled {
      pointer-events: none;
    }

    .letter-heading {
      position: sticky;
      top: 0;
      z-index: 1;
      background: var(--bs-body-bg);
    }
  </style>

  <script>
    (function () {
      const list = document.getElementById('pluginList');
      if (!list) return;

      const items = Array.from(list.querySelectorAll('.plugin-item'));
      const search = document.getElementById('pluginSearch');
      const indexBar = document.getElementById('azIndex');

      // Build map of initials -> items, insert headers
      const groups = new Map();
      for (const item of items) {
        const name = (item.dataset.name || '').trim();
        let initial = name.charAt(0).toUpperCase();
        if (!/^[A-Z]$/.test(initial)) initial = '#';
        item.dataset.initial = initial;
        if (!groups.has(initial)) groups.set(initial, []);
        groups.get(initial).push(item);
      }

      // Insert letter headers
      for (const [initial, groupItems] of groups) {
        const header = document.createElement('div');
        header.className = 'list-group-item letter-heading text-muted fw-semibold';
        header.id = 'plugin-' + initial;
        header.textContent = initial;
        list.insertBefore(header, groupItems[0]);
      }

      // Enable A–Z index and attach smooth scroll
      if (indexBar) {
        indexBar.querySelectorAll('a[data-letter]').forEach(a => {
          const letter = a.getAttribute('data-letter');
          if (groups.has(letter)) {
            a.classList.remove('disabled', 'text-muted');
            a.addEventListener('click', e => {
              e.preventDefault();
              const target = document.getElementById('plugin-' + letter);
              if (target) {
                const yOffset = -70; // adjust this to your navbar height
                const y = target.getBoundingClientRect().top + window.pageYOffset + yOffset;
                window.scrollTo({top: y, behavior: 'smooth'});
              }
            });
          } else {
            a.classList.add('disabled', 'text-muted');
          }
        });
      }
    })();
  </script>
</div>
