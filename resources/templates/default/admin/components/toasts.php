<div class="adm-toasts" aria-live="polite" aria-atomic="false" x-data>
  <template x-for="toast in $store.toast.items" :key="toast.id">
    <div class="adm-toast" :class="'adm-toast--' + toast.type" role="status"
         x-transition:enter="adm-anim-enter" x-transition:enter-start="adm-anim-from" x-transition:leave="adm-anim-leave" x-transition:leave-end="adm-anim-from">
      <i class="bi" :class="toast.icon" aria-hidden="true"></i>
      <p class="adm-toast__message" x-text="toast.message"></p>
      <template x-if="toast.action">
        <button type="button" class="adm-btn adm-btn--ghost adm-btn--sm" x-text="toast.action.label" @click="toast.action.run(); $store.toast.dismiss(toast.id)"></button>
      </template>
      <button type="button" class="adm-icon-btn adm-icon-btn--sm" :aria-label="$t('close')" @click="$store.toast.dismiss(toast.id)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
  </template>
</div>
