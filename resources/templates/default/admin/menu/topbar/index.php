<header class="adm-topbar">
  <a class="adm-topbar__logo" href="{{URL}}/admin" aria-label="{{t:admin.brand}}">
    <span class="adm-logo" aria-hidden="true"><img class="adm-logo__img" src="/storage/images/system/logo-mark.png" alt="" width="128" height="128"></span>
  </a>

  <p class="adm-topbar__title">{{page_title}}</p>

  <form class="adm-search" role="search" action="#" onsubmit="return false">
    <i class="bi bi-search" aria-hidden="true"></i>
    <input type="search" name="q" class="adm-search__input" placeholder="{{t:admin.shell.search_placeholder}}" aria-label="{{t:admin.shell.search}}" autocomplete="off">
  </form>

  <div class="adm-topbar__actions">
    <div class="adm-topbar__locale">{{LOCALE_SWITCHER}}</div>

    <button type="button" class="adm-icon-btn" aria-label="{{t:admin.shell.theme_toggle}}" title="{{t:admin.shell.theme_toggle}}" @click="$store.theme.toggle()">
      <i class="bi bi-moon-stars adm-theme-icon--light" aria-hidden="true"></i>
      <i class="bi bi-sun adm-theme-icon--dark" aria-hidden="true"></i>
    </button>

    <div class="dropdown">
      <button type="button" class="adm-user" id="adm-user-menu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{t:admin.shell.user_menu}}">
        <img class="adm-avatar" src="{{photo}}" alt="" width="32" height="32">
        <span class="adm-user__name">{{customer.name}}</span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end adm-menu" aria-labelledby="adm-user-menu">
        <li><a class="dropdown-item" href="{{URL}}/admin/customer/profile"><i class="bi bi-person" aria-hidden="true"></i>{{t:admin.shell.profile}}</a></li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item adm-menu__danger" href="{{URL}}/admin/logout"
             @click.prevent="if (await $store.confirm.ask({ title: $t('logout_title'), message: $t('logout_text'), confirmLabel: $t('logout_ok') })) window.location.href = $el.href">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>{{t:admin.shell.logout}}
          </a>
        </li>
      </ul>
    </div>
  </div>
</header>
