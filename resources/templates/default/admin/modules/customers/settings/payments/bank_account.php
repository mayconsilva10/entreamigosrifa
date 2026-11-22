<section class="adm-card adm-fsection" aria-labelledby="sec-bank">
  <header class="adm-fsection__head">
    <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-bank"></i></span>
    <div>
      <h2 class="adm-fsection__title" id="sec-bank">{{t:admin.settings.bank.title}}</h2>
      <p class="adm-hint">{{t:admin.settings.bank.subtitle}}</p>
    </div>
  </header>
  <div class="adm-fgrid">
    <div class="adm-field">
      <label class="adm-label" for="bank">{{t:admin.settings.bank.bank}}</label>
      <select class="adm-input" name="bank" id="bank" x-data="bankSelect" data-placeholder="{{t:admin.settings.bank.search}}">
        <option value="">{{t:admin.settings.bank.choose}}</option>
        {{banks}}
      </select>
    </div>
    <div class="adm-field">
      <label class="adm-label" for="type_account">{{t:admin.settings.bank.type}}</label>
      <select class="adm-input" name="type_account" id="type_account">
        <option value="">{{t:admin.settings.bank.choose}}</option>
        {{account_types}}
      </select>
    </div>
    <div class="adm-field">
      <label class="adm-label" for="holder">{{t:admin.settings.bank.holder}}</label>
      <input class="adm-input" type="text" name="holder" id="holder" value="{{holder}}" autocomplete="name">
    </div>
    <div class="adm-field">
      <label class="adm-label" for="document">{{t:admin.settings.bank.document}}</label>
      <input class="adm-input is-num" type="text" name="document" id="document" value="{{document}}" inputmode="numeric" autocomplete="off"
             x-data x-mask:dynamic="$input.replace(/\D/g, '').length > 11 ? '99.999.999/9999-99' : '999.999.999-999'">
    </div>
    <div class="adm-field">
      <label class="adm-label" for="agency">{{t:admin.settings.bank.agency}}</label>
      <input class="adm-input is-num" type="text" name="agency" id="agency" value="{{agency}}" inputmode="numeric" maxlength="10" autocomplete="off">
    </div>
    <div class="adm-field">
      <label class="adm-label" for="account">{{t:admin.settings.bank.account}}</label>
      <input class="adm-input is-num" type="text" name="account" id="account" value="{{account}}" inputmode="numeric" maxlength="20" autocomplete="off">
    </div>
  </div>
</section>
