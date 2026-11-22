<form class="adm-auth__form" action="{{URL}}/newCustomer" method="post" data-adm-submit @submit="checkRegister($event)">
  <div class="adm-field">
    <label class="adm-label" for="register-name">{{t:admin.auth.fields.name}}</label>
    <input type="text" name="username" id="register-name" class="adm-input" autocomplete="name" maxlength="150" required>
  </div>
  <div class="adm-field">
    <label class="adm-label" for="register-email">{{t:admin.auth.fields.email}}</label>
    <input type="email" name="email" id="register-email" class="adm-input" autocomplete="email" required>
  </div>
  <div class="adm-field">
    <label class="adm-label" for="register-phone">{{t:admin.auth.fields.phone}}</label>
    <input type="tel" name="phone" id="register-phone" class="adm-input adm-num" x-mask="(99) 99999-9999" inputmode="numeric" maxlength="15" autocomplete="tel-national" placeholder="(11) 91234-5678" required>
  </div>
  <div class="adm-field">
    <label class="adm-label" for="register-password">{{t:admin.auth.fields.password}}</label>
    <div class="adm-input-group">
      <input :type="show ? 'text' : 'password'" type="password" name="password" id="register-password" class="adm-input" autocomplete="new-password" minlength="6" required x-model="password">
      <button type="button" class="adm-btn adm-btn--secondary" @click="show = !show" :aria-pressed="show.toString()" aria-label="{{t:admin.auth.fields.show_password}}">
        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'" aria-hidden="true"></i>
      </button>
    </div>
  </div>
  <div class="adm-field">
    <label class="adm-label" for="register-confirm">{{t:admin.auth.fields.confirm_password}}</label>
    <input :type="show ? 'text' : 'password'" type="password" name="confirm-password" id="register-confirm" class="adm-input" autocomplete="new-password" required
           x-model="confirm" :aria-invalid="mismatch.toString()" aria-describedby="register-confirm-msg">
    <p class="adm-field__error" id="register-confirm-msg" x-show="mismatch" x-cloak><i class="bi bi-exclamation-circle" aria-hidden="true"></i>{{t:admin.auth.register.mismatch}}</p>
  </div>
  <button type="submit" class="adm-btn adm-btn--primary adm-auth__submit"><span>{{t:admin.auth.register.submit}}</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
  <p class="adm-hint adm-auth__legal">{{t!:admin.auth.register.legal}}</p>
</form>
