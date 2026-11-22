{{header}}

<div class="adm-settings" x-data="{ navOpen: false }">
  <nav class="adm-settings__nav" aria-label="{{t:admin.settings.nav.label}}">
    <button type="button" class="adm-settings__toggle" @click="navOpen = !navOpen" :aria-expanded="navOpen" aria-controls="adm-settings-list">
      <i class="bi {{current_icon}}" aria-hidden="true"></i>
      <span class="adm-settings__toggle-label">{{current_label}}</span>
      <span class="adm-settings__toggle-hint">{{t:admin.settings.nav.switch}}</span>
      <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </button>
    <ul class="adm-settings__list" id="adm-settings-list" :class="navOpen && 'is-open'">
      {{items}}
    </ul>
  </nav>

  <div class="adm-settings__content">
    {{flash}}
    {{content}}
  </div>
</div>
