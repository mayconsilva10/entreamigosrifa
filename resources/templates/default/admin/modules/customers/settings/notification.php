<form class="adm-settings-form" action="/admin/customer/notification" method="post" data-adm-submit>
  <section class="adm-card adm-fsection" aria-labelledby="sec-notification">
    <header class="adm-fsection__head">
      <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-bell"></i></span>
      <div>
        <h2 class="adm-fsection__title" id="sec-notification">{{t:admin.settings.notification.title}}</h2>
        <p class="adm-hint">{{t:admin.settings.notification.subtitle}}</p>
      </div>
    </header>
    <div class="adm-toggle-list">
      {{telegram}}
    </div>
  </section>

  <div class="adm-formbar">
    <button type="submit" class="adm-btn adm-btn--primary"><i class="bi bi-check2" aria-hidden="true"></i><span>{{t:admin.settings.save}}</span></button>
  </div>
</form>
