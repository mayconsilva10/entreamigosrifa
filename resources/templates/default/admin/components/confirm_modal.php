<div class="adm-dialog" x-data x-cloak x-show="$store.confirm.open" @keydown.escape.window="$store.confirm.open && $store.confirm.resolve(false)"
     @keydown.tab.window="$store.confirm.open && $store.confirm.trap($event, $el)"
     role="alertdialog" aria-modal="true" aria-labelledby="adm-confirm-title" aria-describedby="adm-confirm-text">
  <div class="adm-dialog__backdrop" x-show="$store.confirm.open" x-transition.opacity @click="$store.confirm.resolve(false)"></div>
  <div class="adm-dialog__panel" x-show="$store.confirm.open" x-transition:enter="adm-anim-enter" x-transition:enter-start="adm-anim-from">
    <span class="adm-dialog__icon" :class="$store.confirm.danger ? 'adm-dialog__icon--danger' : ''" aria-hidden="true">
      <i class="bi" :class="$store.confirm.danger ? 'bi-exclamation-triangle-fill' : 'bi-question-circle-fill'"></i>
    </span>
    <h2 class="adm-dialog__title" id="adm-confirm-title" x-text="$store.confirm.title"></h2>
    <p class="adm-dialog__text" id="adm-confirm-text" x-text="$store.confirm.message"></p>
    <div class="adm-dialog__actions">
      <button type="button" class="adm-btn adm-btn--secondary" @click="$store.confirm.resolve(false)" x-text="$store.confirm.cancelLabel"></button>
      <button type="button" class="adm-btn" :class="$store.confirm.danger ? 'adm-btn--danger' : 'adm-btn--primary'" @click="$store.confirm.resolve(true)" x-text="$store.confirm.confirmLabel" data-confirm-ok></button>
    </div>
  </div>
</div>
