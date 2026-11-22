<section class="adm-card adm-fsection adm-history" aria-labelledby="sec-history">
  <header class="adm-fsection__head">
    <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
    <div>
      <h2 class="adm-fsection__title" id="sec-history">{{t:admin.history.title}}</h2>
      <p class="adm-hint">{{t:admin.history.subtitle}}</p>
    </div>
  </header>

  <form class="adm-toolbar" action="{{action}}" method="get" role="search" x-data>
    {{hidden}}
    <div class="adm-toolbar__row">
      <div class="adm-toolbar__search">
        <label class="visually-hidden" for="adm-history-q">{{t:admin.history.search_label}}</label>
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" id="adm-history-q" name="q" class="adm-input" value="{{q}}" placeholder="{{t:admin.history.search_placeholder}}" autocomplete="off" maxlength="100"
               @input.debounce.500ms="$el.form.requestSubmit()">
        <button type="submit" class="visually-hidden">{{t:admin.raffles.toolbar.submit}}</button>
      </div>
    </div>
    <div class="adm-toolbar__row">
      <nav class="adm-chips" aria-label="{{t:admin.history.filters}}">{{chips}}</nav>
    </div>
  </form>

  {{body}}
  {{pagination}}
</section>
