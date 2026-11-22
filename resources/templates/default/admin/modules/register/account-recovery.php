<div class="adm-auth">
  {{brand}}

  <main class="adm-auth__main" id="adm-main">
    <div class="adm-auth__top">{{LOCALE_SWITCHER}}</div>
    <div class="adm-auth__panel">
      <span class="adm-auth__icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
      <h1 class="adm-auth__title">{{t:admin.auth.recovery.title}}</h1>
      <p class="adm-hint adm-auth__subtitle">{{t:admin.auth.recovery.text}}</p>
      <a class="adm-btn adm-btn--primary adm-auth__submit" href="{{support_url}}" target="_blank" rel="noopener"><i class="bi bi-headset" aria-hidden="true"></i><span>{{t:admin.auth.recovery.support}}</span></a>
      <p class="adm-auth__back"><a class="adm-auth__link" href="{{URL}}/admin/login"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{t:admin.auth.recovery.back}}</a></p>
    </div>
  </main>
</div>
