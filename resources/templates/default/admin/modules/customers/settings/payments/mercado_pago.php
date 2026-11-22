<section class="adm-card adm-fsection" aria-labelledby="sec-mp" x-data="{ show: false }">
  <header class="adm-fsection__head">
    <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-credit-card"></i></span>
    <div>
      <h2 class="adm-fsection__title" id="sec-mp">{{t:admin.settings.mp.title}}</h2>
      <p class="adm-hint">{{t:admin.settings.mp.subtitle}}</p>
    </div>
    <div class="adm-fsection__aside">{{token_state}}</div>
  </header>
  <div class="adm-field">
    <label class="adm-label" for="public_key">{{t:admin.settings.mp.public_key}}</label>
    <input class="adm-input is-mono" type="text" name="public_key" id="public_key" value="{{public_key}}" autocomplete="off" spellcheck="false" placeholder="APP_USR-…">
  </div>
  <div class="adm-field">
    <label class="adm-label" for="access_token">{{t:admin.settings.mp.access_token}}</label>
    <div class="adm-input-group">
      <input class="adm-input is-mono" :type="show ? 'text' : 'password'" type="password" name="access_token" id="access_token" value="{{access_token}}"
             autocomplete="off" spellcheck="false" aria-describedby="token-hint">
      <button type="button" class="adm-input-group__btn" @click="show = !show" :aria-pressed="show" :aria-label="show ? $t('password_hide') : $t('password_show')" :title="show ? $t('password_hide') : $t('password_show')">
        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
      </button>
    </div>
    <span class="adm-hint" id="token-hint">{{t:admin.settings.mp.token_hint}}</span>
  </div>
</section>
