<form class="adm-settings-form" action="/admin/customer/editProfile" method="post" enctype="multipart/form-data" data-adm-submit>
  <section class="adm-card adm-fsection" aria-labelledby="sec-photo">
    <header class="adm-fsection__head">
      <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-image"></i></span>
      <div>
        <h2 class="adm-fsection__title" id="sec-photo">{{t:admin.settings.profile.photo}}</h2>
        <p class="adm-hint">{{t:admin.settings.profile.photo_hint}}</p>
      </div>
    </header>
    <div class="adm-avatar-field" x-data="{ preview: '' }">
      <img class="adm-avatar-field__img" :src="preview || $el.dataset.src" data-src="{{photo_profile}}" src="{{photo_profile}}" alt="{{t:admin.settings.profile.photo_alt}}" width="88" height="88">
      <div>
        <label class="adm-btn adm-btn--secondary" for="photo_profile"><i class="bi bi-upload" aria-hidden="true"></i>{{t:admin.settings.profile.photo_choose}}</label>
        <input class="visually-hidden" type="file" name="photo_profile" id="photo_profile" accept="image/jpeg,image/png,image/webp" data-max-size="{{max_kb}}"
               @change="const file = $event.target.files[0]; if (file) preview = URL.createObjectURL(file)" aria-describedby="photo-hint">
        <p class="adm-hint" id="photo-hint">{{t:admin.settings.profile.photo_limit}}</p>
      </div>
    </div>
  </section>

  <section class="adm-card adm-fsection" aria-labelledby="sec-account">
    <header class="adm-fsection__head">
      <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
      <div>
        <h2 class="adm-fsection__title" id="sec-account">{{t:admin.settings.profile.account}}</h2>
        <p class="adm-hint">{{t:admin.settings.profile.account_hint}}</p>
      </div>
    </header>
    <div class="adm-field">
      <label class="adm-label" for="name">{{t:admin.settings.profile.name}}</label>
      <input class="adm-input" type="text" name="name" id="name" value="{{name}}" autocomplete="name" maxlength="255" aria-describedby="name-hint">
      <span class="adm-hint" id="name-hint">{{t:admin.settings.profile.name_hint}}</span>
    </div>
    <div class="adm-fgrid">
      <div class="adm-field">
        <label class="adm-label" for="email">{{t:admin.settings.profile.email}}</label>
        <input class="adm-input" type="email" id="email" value="{{email}}" readonly aria-readonly="true" aria-describedby="email-hint">
        <span class="adm-lock" id="email-hint"><i class="bi bi-lock-fill" aria-hidden="true"></i>{{t:admin.settings.profile.email_locked}}</span>
      </div>
      <div class="adm-field">
        <label class="adm-label" for="phone">{{t:admin.settings.profile.phone}}</label>
        <input class="adm-input" type="tel" name="phone" id="phone" value="{{phone}}" autocomplete="tel" inputmode="tel" required
               placeholder="(00) 00000-0000" x-data x-mask:dynamic="$input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-99999'">
      </div>
    </div>
  </section>

  <div class="adm-formbar">
    <button type="submit" class="adm-btn adm-btn--primary"><i class="bi bi-check2" aria-hidden="true"></i><span>{{t:admin.settings.save}}</span></button>
  </div>
</form>
