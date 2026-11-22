<form class="adm-toolbar" action="{{action}}" method="get" role="search" x-data>
  {{hidden}}
  <div class="adm-toolbar__row">
    <div class="adm-toolbar__search">
      <label class="visually-hidden" for="adm-toolbar-q">{{search_label}}</label>
      <i class="bi bi-search" aria-hidden="true"></i>
      <input type="search" id="adm-toolbar-q" name="q" class="adm-input" value="{{q}}" placeholder="{{search_placeholder}}" autocomplete="off" maxlength="100" {{autofocus}}
             @input.debounce.500ms="$el.form.requestSubmit()" x-init="$el.hasAttribute('autofocus') && $el.setSelectionRange($el.value.length, $el.value.length)">
      <button type="submit" class="visually-hidden">{{submit}}</button>
    </div>
    <div class="adm-toolbar__sort">
      <label class="adm-toolbar__label" for="adm-toolbar-sort">{{sort_label}}</label>
      <select id="adm-toolbar-sort" name="sort" class="adm-input adm-select" @change="$el.form.requestSubmit()">{{sorts}}</select>
    </div>
  </div>
  <div class="adm-toolbar__row">
    <nav class="adm-chips" aria-label="{{chips_label}}">{{chips}}</nav>
    {{clear}}
  </div>
</form>
