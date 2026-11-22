{{header}}

<form id="adm-raffle-form" class="adm-rform" method="post" enctype="multipart/form-data" x-data="raffleForm" @submit="onSubmit($event)">
  <div class="adm-rform__main">
    <!-- 1. Prêmio -->
    <section class="adm-card adm-fsection" aria-labelledby="sec-prize">
      <header class="adm-fsection__head">
        <span class="adm-fsection__step" aria-hidden="true">1</span>
        <div>
          <h2 class="adm-fsection__title" id="sec-prize">{{t:admin.raffle_form.sections.prize}}</h2>
          <p class="adm-hint">{{t:admin.raffle_form.sections.prize_hint}}</p>
        </div>
      </header>

      <div class="adm-field">
        <label class="adm-label" for="title">{{t:admin.raffle_form.fields.title}} <span class="adm-req" aria-hidden="true">*</span></label>
        <input class="adm-input" type="text" name="title" id="title" value="{{raffle_title}}" maxlength="255" required
               placeholder="{{t:admin.raffle_form.fields.title_placeholder}}" x-model="title" {{inv_title}} aria-describedby="title-hint{{desc_title}}">
        <div class="adm-field__meta">
          <span class="adm-hint" id="title-hint">{{t:admin.raffle_form.fields.title_hint}}</span>
          <span class="adm-hint adm-field__count" x-text="`${title.length}/255`"></span>
        </div>
        {{err_title}}
      </div>

      <div class="adm-field">
        <label class="adm-label" for="description">{{t:admin.raffle_form.fields.description}}</label>
        <textarea class="adm-input" name="description" id="description" rows="5" {{inv_description}} aria-describedby="description-hint{{desc_description}}">{{description}}</textarea>
        <span class="adm-hint" id="description-hint">{{t:admin.raffle_form.fields.description_hint}}</span>
        {{err_description}}
      </div>

      <div class="adm-field">
        <span class="adm-label" id="photos-label">{{t:admin.raffle_form.fields.photos}}</span>

        <ul class="adm-photos" x-show="photos.length || files.length" x-cloak aria-labelledby="photos-label">
          <template x-for="photo in photos" :key="'p' + photo.key">
            <li class="adm-photo">
              <img :src="photo.url" alt="" loading="lazy">
              <button type="button" class="adm-photo__remove" @click="removePhoto(photo)" :disabled="busy" :aria-label="$t('form_photo_remove')" :title="$t('form_photo_remove')">
                <i class="bi bi-trash" aria-hidden="true"></i>
              </button>
            </li>
          </template>
          <template x-for="item in files" :key="item.id">
            <li class="adm-photo is-new">
              <img :src="item.url" alt="">
              <span class="adm-photo__tag" x-text="$t('form_photo_new')"></span>
              <button type="button" class="adm-photo__remove" @click="removeFile(item.id)" :aria-label="$t('form_photo_discard', { name: item.name })" :title="$t('form_photo_discard', { name: item.name })">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
              </button>
            </li>
          </template>
        </ul>

        <label class="adm-dropzone" :class="dragging && 'is-dragging'" for="photos"
               @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop($event)">
          <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
          <span class="adm-dropzone__title">{{t:admin.raffle_form.fields.photos_drop}}</span>
          <span class="adm-hint" id="photos-hint">{{t:admin.raffle_form.fields.photos_hint}}</span>
          <input class="adm-dropzone__input" type="file" name="photos[]" id="photos" multiple accept="image/jpeg,image/png,image/webp"
                 x-ref="files" @change="onPick($event)" {{inv_photos}} aria-describedby="photos-hint{{desc_photos}}">
        </label>
        {{err_photos}}
      </div>
    </section>

    <!-- 2. Números e preço -->
    <section class="adm-card adm-fsection" aria-labelledby="sec-numbers">
      <header class="adm-fsection__head">
        <span class="adm-fsection__step" aria-hidden="true">2</span>
        <div>
          <h2 class="adm-fsection__title" id="sec-numbers">{{t:admin.raffle_form.sections.numbers}}</h2>
          <p class="adm-hint">{{t:admin.raffle_form.sections.numbers_hint}}</p>
        </div>
      </header>

      <div class="adm-fgrid">
        <div class="adm-field">
          <label class="adm-label" for="generated_numbers">{{t:admin.raffle_form.fields.numbers}} <span class="adm-req" aria-hidden="true">*</span></label>
          <input class="adm-input is-num" type="number" name="generated_numbers" id="generated_numbers" value="{{generated_numbers}}"
                 min="1" max="{{numbers_max}}" step="1" inputmode="numeric" required x-model.number="total"
                 {{numbers_locked}} {{inv_generated_numbers}} aria-describedby="numbers-hint{{desc_generated_numbers}}">
          <div class="adm-presets" x-show="!locked" role="group" aria-label="{{t:admin.raffle_form.fields.numbers_presets}}">
            <template x-for="preset in presets" :key="preset">
              <button type="button" class="adm-chip" :class="Number(total) === preset && 'is-active'" :aria-pressed="Number(total) === preset"
                      @click="total = preset" x-text="int(preset)"></button>
            </template>
          </div>
          <p class="adm-hint" id="numbers-hint">
            <span x-show="!locked">{{t:admin.raffle_form.fields.numbers_hint}}</span>
            <span class="adm-lock" x-show="locked" x-cloak><i class="bi bi-lock-fill" aria-hidden="true"></i>{{t:admin.raffle_form.fields.numbers_locked}}</span>
          </p>
          {{err_generated_numbers}}
        </div>

        <div class="adm-field">
          <label class="adm-label" for="price">{{t:admin.raffle_form.fields.price}} <span class="adm-req" aria-hidden="true">*</span></label>
          <div class="adm-input-group">
            <span class="adm-input-group__addon" aria-hidden="true">{{currency_symbol}}</span>
            <input class="adm-input is-num" type="text" name="price" id="price" value="{{price}}" inputmode="decimal" autocomplete="off" required
                   placeholder="0{{decimal_sep}}00" x-model="price"
                   x-mask:dynamic="$money($input, decimal, thousands)" {{inv_price}} aria-describedby="price-hint{{desc_price}}">
          </div>
          <span class="adm-hint" id="price-hint">{{t:admin.raffle_form.fields.price_hint}}</span>
          {{err_price}}
        </div>
      </div>
    </section>

    <!-- 3. Sorteio -->
    <section class="adm-card adm-fsection" aria-labelledby="sec-draw">
      <header class="adm-fsection__head">
        <span class="adm-fsection__step" aria-hidden="true">3</span>
        <div>
          <h2 class="adm-fsection__title" id="sec-draw">{{t:admin.raffle_form.sections.draw}}</h2>
          <p class="adm-hint">{{t:admin.raffle_form.sections.draw_hint}}</p>
        </div>
      </header>

      <div class="adm-field adm-field--narrow">
        <label class="adm-label" for="date_sortition">{{t:admin.raffle_form.fields.date}}</label>
        <input class="adm-input" type="datetime-local" name="date_sortition" id="date_sortition" value="{{date_sortition}}" min="{{min_date}}"
               x-model="drawDate" {{inv_date_sortition}} aria-describedby="date-hint{{desc_date_sortition}}">
        <span class="adm-hint" id="date-hint" aria-live="polite" x-text="drawLabel || $t('form_date_empty')"></span>
        {{err_date_sortition}}
      </div>

      <div class="adm-field">
        <label class="adm-label" for="draw_rules">{{t:admin.raffle_form.fields.rules}}</label>
        <textarea class="adm-input" name="draw_rules" id="draw_rules" rows="5" {{inv_draw_rules}} aria-describedby="rules-hint{{desc_draw_rules}}">{{draw_rules}}</textarea>
        <span class="adm-hint" id="rules-hint">{{t:admin.raffle_form.fields.rules_hint}}</span>
        {{err_draw_rules}}
      </div>
    </section>
  </div>

  <!-- Resumo ao vivo: lateral no desktop, colapsável no mobile -->
  <aside class="adm-rform__aside" aria-labelledby="adm-summary-title">
    <div class="adm-card adm-summary">
      <button type="button" class="adm-summary__toggle" @click="summaryOpen = !summaryOpen" :aria-expanded="summaryOpen" aria-controls="adm-summary-body">
        <span class="adm-summary__title" id="adm-summary-title">{{t:admin.raffle_form.summary.title}}</span>
        <span class="adm-summary__peek is-num" x-text="money(potential)"></span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
      </button>
      <div class="adm-summary__body" id="adm-summary-body" :class="summaryOpen && 'is-open'">
        <p class="adm-summary__formula is-num" x-text="$t('form_summary_formula', { count: int(totalInt), price: money(priceValue) })"></p>
        <dl class="adm-summary__list">
          <div>
            <dt>{{t:admin.raffle_form.summary.potential}}</dt>
            <dd class="is-num adm-summary__big" x-text="money(potential)"></dd>
          </div>
          <div x-show="showFee">
            <dt>{{t:admin.raffle_form.summary.fee}}</dt>
            <dd class="is-num" x-text="money(releaseCost)"></dd>
          </div>
          <div x-show="showFee">
            <dt>{{t:admin.raffle_form.summary.net}}</dt>
            <dd class="is-num" x-text="money(potential - releaseCost)"></dd>
          </div>
          <div>
            <dt>{{t:admin.raffle_form.summary.draw}}</dt>
            <dd x-text="drawLabel || $t('form_date_empty')"></dd>
          </div>
        </dl>
        <p class="adm-hint" x-show="showFee" x-text="$t('form_summary_fee_hint', { fee: money(fee) })"></p>
      </div>
    </div>
  </aside>

  <div class="adm-formbar">
    <a class="adm-btn adm-btn--ghost" href="{{cancel_url}}">{{t:admin.raffle_form.cancel}}</a>
    <button type="submit" class="adm-btn adm-btn--primary" :class="submitting && 'is-loading'" :disabled="submitting">
      <i class="bi bi-check2" aria-hidden="true"></i><span>{{submit_label}}</span>
    </button>
  </div>
</form>

<script type="application/json" id="adm-raffle-form-data">{{data}}</script>

<script src="/vendor/tinymce/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  (function () {
    if (!window.tinymce) return;
    var dark = document.documentElement.getAttribute('data-theme') === 'dark';
    var options = {
      height: 260,
      menubar: false,
      promotion: false,
      branding: false,
      skin: dark ? 'oxide-dark' : 'oxide',
      content_css: dark ? 'dark' : 'default',
      plugins: ['link', 'lists', 'autoresize'],
      toolbar: 'undo redo | bold italic | bullist numlist | link',
    };
    tinymce.init(Object.assign({ selector: 'textarea#description' }, options));
    tinymce.init(Object.assign({ selector: 'textarea#draw_rules' }, options));
  })();
</script>
