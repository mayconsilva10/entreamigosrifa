{{header}}
{{flash}}

<div class="adm-raffles" x-data="raffleList" data-share-base="{{share_base}}" data-link-endpoint="{{link_endpoint}}" data-link-max="{{link_max}}"
     @keydown.escape.window="closeDialog()">
  <section class="adm-card adm-raffles__panel" aria-label="{{t:admin.raffles.table.caption}}">
    {{toolbar}}
    {{body}}
    {{pagination}}
  </section>

  <!-- Compartilhar -->
  <div class="adm-dialog" x-cloak x-show="dialog === 'share'" data-dialog="share" role="dialog" aria-modal="true" aria-labelledby="adm-share-title">
    <div class="adm-dialog__backdrop" x-show="dialog === 'share'" x-transition.opacity @click="closeDialog()"></div>
    <div class="adm-dialog__panel adm-dialog__panel--wide" x-show="dialog === 'share'" x-transition:enter="adm-anim-enter" x-transition:enter-start="adm-anim-from">
      <button type="button" class="adm-icon-btn adm-icon-btn--sm adm-dialog__close" @click="closeDialog()" aria-label="{{t:admin.raffles.dialog.close}}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
      <span class="adm-dialog__icon" aria-hidden="true"><i class="bi bi-share-fill"></i></span>
      <h2 class="adm-dialog__title" id="adm-share-title">{{t:admin.raffles.share.title}}</h2>
      <p class="adm-dialog__text"><strong x-text="raffle.title"></strong> · {{t:admin.raffles.share.text}}</p>
      <div class="adm-field">
        <label class="adm-label" for="adm-share-url">{{t:admin.raffles.share.link_label}}</label>
        <div class="adm-input-group">
          <input type="text" id="adm-share-url" class="adm-input" :value="raffle.url" readonly @focus="$el.select()" data-autofocus>
          <button type="button" class="adm-btn adm-btn--primary" x-copy="raffle.url"><i class="bi bi-files" aria-hidden="true"></i>{{t:admin.raffles.share.copy}}</button>
        </div>
      </div>
      <div class="adm-dialog__actions adm-dialog__actions--split">
        <a class="adm-btn adm-btn--whatsapp" :href="whatsappUrl" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i>{{t:admin.raffles.share.whatsapp}}</a>
        <button type="button" class="adm-btn adm-btn--secondary" x-show="canShare" @click="nativeShare()"><i class="bi bi-box-arrow-up" aria-hidden="true"></i>{{t:admin.raffles.share.native}}</button>
      </div>
    </div>
  </div>

  <!-- Personalizar link -->
  <div class="adm-dialog" x-cloak x-show="dialog === 'link'" data-dialog="link" role="dialog" aria-modal="true" aria-labelledby="adm-link-title" aria-describedby="adm-link-text">
    <div class="adm-dialog__backdrop" x-show="dialog === 'link'" x-transition.opacity @click="closeDialog()"></div>
    <form class="adm-dialog__panel adm-dialog__panel--wide" x-show="dialog === 'link'" x-transition:enter="adm-anim-enter" x-transition:enter-start="adm-anim-from" @submit.prevent="saveLink()" novalidate>
      <button type="button" class="adm-icon-btn adm-icon-btn--sm adm-dialog__close" @click="closeDialog()" aria-label="{{t:admin.raffles.dialog.close}}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
      <span class="adm-dialog__icon" aria-hidden="true"><i class="bi bi-link-45deg"></i></span>
      <h2 class="adm-dialog__title" id="adm-link-title">{{t:admin.raffles.link.title}}</h2>
      <p class="adm-dialog__text" id="adm-link-text">{{t:admin.raffles.link.text}}</p>
      <div class="adm-field">
        <label class="adm-label" for="adm-link-code">{{t:admin.raffles.link.label}}</label>
        <div class="adm-input-group">
          <span class="adm-input-group__prefix" x-text="shareBase.replace(/^https?:\/\//, '')"></span>
          <input type="text" id="adm-link-code" class="adm-input" :value="code" @input="onCodeInput($event)" :aria-invalid="error ? 'true' : 'false'"
                 aria-describedby="adm-link-hint adm-link-error" autocomplete="off" autocapitalize="none" spellcheck="false" :maxlength="maxLength" data-autofocus>
        </div>
        <p class="adm-hint" id="adm-link-hint">{{t:admin.raffles.link.hint}} {{t:admin.raffles.link.preview}} <strong class="adm-break" x-text="preview"></strong></p>
        <p class="adm-field__error" id="adm-link-error" role="alert" x-show="error" x-text="error"></p>
      </div>
      <div class="adm-dialog__actions">
        <button type="button" class="adm-btn adm-btn--secondary" @click="closeDialog()">{{t:admin.raffles.dialog.cancel}}</button>
        <button type="submit" class="adm-btn adm-btn--primary" :class="saving && 'is-loading'" :disabled="saving">{{t:admin.raffles.link.save}}</button>
      </div>
    </form>
  </div>

  <!-- Excluir (digitar o título para confirmar) -->
  <div class="adm-dialog" x-cloak x-show="dialog === 'delete'" data-dialog="delete" role="alertdialog" aria-modal="true" aria-labelledby="adm-delete-title" aria-describedby="adm-delete-text">
    <div class="adm-dialog__backdrop" x-show="dialog === 'delete'" x-transition.opacity @click="closeDialog()"></div>
    <form class="adm-dialog__panel adm-dialog__panel--wide" action="{{URL}}/admin/sortition/delete" method="post" x-show="dialog === 'delete'"
          x-transition:enter="adm-anim-enter" x-transition:enter-start="adm-anim-from" @submit="deleteMatches ? (deleting = true) : $event.preventDefault()">
      <input type="hidden" name="idDeleteSortition" :value="raffle.id">
      <button type="button" class="adm-icon-btn adm-icon-btn--sm adm-dialog__close" @click="closeDialog()" aria-label="{{t:admin.raffles.dialog.close}}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
      <span class="adm-dialog__icon adm-dialog__icon--danger" aria-hidden="true"><i class="bi bi-trash-fill"></i></span>
      <h2 class="adm-dialog__title" id="adm-delete-title">{{t:admin.raffles.delete.title}}</h2>
      <p class="adm-dialog__text" id="adm-delete-text"><strong x-text="raffle.title"></strong> · {{t:admin.raffles.delete.text}}</p>
      <div class="adm-field">
        <label class="adm-label" for="adm-delete-confirm">{{t:admin.raffles.delete.label}}</label>
        <input type="text" id="adm-delete-confirm" class="adm-input" x-model="confirmText" :placeholder="raffle.title" autocomplete="off" data-autofocus>
      </div>
      <div class="adm-dialog__actions">
        <button type="button" class="adm-btn adm-btn--secondary" @click="closeDialog()">{{t:admin.raffles.dialog.cancel}}</button>
        <button type="submit" class="adm-btn adm-btn--danger" :class="deleting && 'is-loading'" :disabled="!deleteMatches || deleting">{{t:admin.raffles.delete.confirm}}</button>
      </div>
    </form>
  </div>
</div>
