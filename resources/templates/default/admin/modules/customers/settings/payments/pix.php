<section class="adm-card adm-fsection" aria-labelledby="sec-pix" x-data="pixKey" data-type="{{type_key}}">
  <header class="adm-fsection__head">
    <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-qr-code"></i></span>
    <div>
      <h2 class="adm-fsection__title" id="sec-pix">{{t:admin.settings.pix.title}}</h2>
      <p class="adm-hint">{{t:admin.settings.pix.subtitle}}</p>
    </div>
  </header>
  <div class="adm-fgrid">
    <div class="adm-field">
      <label class="adm-label" for="type_key_pix">{{t:admin.settings.pix.type}}</label>
      <select class="adm-input" id="type_key_pix" name="type_key" x-model="type" @change="onTypeChange()">
        <option value="">{{t:admin.settings.pix.choose}}</option>
        {{options}}
      </select>
    </div>
    <div class="adm-field">
      <label class="adm-label" for="pix_key">{{t:admin.settings.pix.key}}</label>
      <input class="adm-input" :type="type === 'email' ? 'email' : 'text'" type="text" name="pix_key" id="pix_key" value="{{pix_key}}" x-ref="key"
             :inputmode="inputMode" :placeholder="placeholder" autocomplete="off" spellcheck="false"
             x-mask:dynamic="mask($input)" aria-describedby="pix-hint">
      <span class="adm-hint" id="pix-hint" x-text="hint">{{t:admin.settings.pix.hint}}</span>
    </div>
  </div>
</section>
