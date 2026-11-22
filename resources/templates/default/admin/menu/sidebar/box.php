<aside class="adm-sidebar" id="adm-sidebar">
  <a class="adm-sidebar__brand" href="{{URL}}/admin">
    <span class="adm-logo" aria-hidden="true"><img class="adm-logo__img" src="/storage/images/system/logo-mark.png" alt="" width="128" height="128"></span>
    <span class="adm-sidebar__label">{{t:admin.brand}}</span>
  </a>

  <a class="adm-btn adm-btn--primary adm-sidebar__cta" href="{{URL}}/admin/sortition/create" title="{{t:admin.nav.new_raffle}}">
    <i class="bi bi-plus-lg" aria-hidden="true"></i>
    <span class="adm-sidebar__label">{{t:admin.nav.new_raffle}}</span>
  </a>

  <nav class="adm-sidebar__nav" aria-label="{{t:admin.nav.main}}">
    <ul class="adm-nav">
      {{links}}
    </ul>
  </nav>

  <button type="button" class="adm-sidebar__toggle" aria-controls="adm-sidebar" aria-label="{{t:admin.nav.collapse}}"
          :aria-pressed="$store.shell.collapsed.toString()" @click="$store.shell.toggleSidebar()">
    <i class="bi bi-chevron-double-left" aria-hidden="true"></i>
    <span class="adm-sidebar__label">{{t:admin.nav.collapse}}</span>
  </button>
</aside>
