<form class="adm-auth__form" method="post" action="{{URL}}/admin/login" data-adm-submit>
  <div class="adm-field">
    <label class="adm-label" for="login-email">{{t:admin.auth.fields.email}}</label>
    <input type="email" name="email" id="login-email" class="adm-input" value="{{email}}" autocomplete="email" required autofocus>
  </div>
  <div class="adm-field">
    <div class="adm-auth__label-row">
      <label class="adm-label" for="login-password">{{t:admin.auth.fields.password}}</label>
      <a class="adm-auth__link" href="{{URL}}/admin/account-recovery">{{t:admin.auth.login.forgot}}</a>
    </div>
    <div class="adm-input-group">
      <input :type="show ? 'text' : 'password'" type="password" name="password" id="login-password" class="adm-input" autocomplete="current-password" required>
      <button type="button" class="adm-btn adm-btn--secondary" @click="show = !show" :aria-pressed="show.toString()" aria-label="{{t:admin.auth.fields.show_password}}">
        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
      </button>
    </div>
  </div>
  <button type="submit" class="adm-btn adm-btn--primary adm-auth__submit"><span>{{t:admin.auth.login.submit}}</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
</form>
