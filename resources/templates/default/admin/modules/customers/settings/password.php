<form class="adm-settings-form" action="/admin/customer/setPassword" method="post" data-adm-submit x-data="passwordForm" @submit="onSubmit($event)">
  <section class="adm-card adm-fsection" aria-labelledby="sec-password">
    <header class="adm-fsection__head">
      <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
      <div>
        <h2 class="adm-fsection__title" id="sec-password">{{t:admin.settings.password.title}}</h2>
        <p class="adm-hint">{{t:admin.settings.password.subtitle}}</p>
      </div>
    </header>

    <div class="adm-field adm-field--narrow">
      <label class="adm-label" for="new_pass">{{t:admin.settings.password.new}}</label>
      <div class="adm-input-group">
        <input class="adm-input" :type="show ? 'text' : 'password'" type="password" name="new_pass" id="new_pass" autocomplete="new-password" required
               x-model="password" aria-describedby="strength-label">
        <button type="button" class="adm-input-group__btn" @click="show = !show" :aria-pressed="show" :aria-label="show ? $t('password_hide') : $t('password_show')" :title="show ? $t('password_hide') : $t('password_show')">
          <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
        </button>
      </div>
      <div class="adm-strength" :data-score="score" x-show="password.length" x-cloak>
        <span class="adm-strength__bar" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
        <span class="adm-hint" id="strength-label" aria-live="polite" x-text="strengthLabel"></span>
      </div>
      <span class="adm-hint" x-show="!password.length">{{t:admin.settings.password.tips}}</span>
    </div>

    <div class="adm-field adm-field--narrow">
      <label class="adm-label" for="confir_new_pass">{{t:admin.settings.password.confirm}}</label>
      <input class="adm-input" :type="show ? 'text' : 'password'" type="password" name="confir_new_pass" id="confir_new_pass" autocomplete="new-password" required
             x-model="confirmation" :aria-invalid="mismatch" aria-describedby="match-hint">
      <p class="adm-field__error" id="match-hint" x-show="mismatch" x-cloak><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>{{t:admin.settings.password.mismatch}}</p>
      <p class="adm-field__ok" x-show="matches" x-cloak><i class="bi bi-check-circle-fill" aria-hidden="true"></i>{{t:admin.settings.password.match}}</p>
    </div>
  </section>

  <div class="adm-formbar">
    <button type="submit" class="adm-btn adm-btn--primary"><i class="bi bi-check2" aria-hidden="true"></i><span>{{t:admin.settings.password.save}}</span></button>
  </div>
</form>
