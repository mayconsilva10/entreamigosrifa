{{header}}
{{flash}}

<div class="adm-manage" x-data="raffleManage" @keydown.escape.window="onEscape()">
  <section class="adm-kpis adm-kpis--mini" aria-label="{{t:admin.manage.kpi.region}}">
    <article class="adm-kpi adm-kpi--mini">
      <div class="adm-kpi__head">
        <span class="adm-kpi__label">{{t:admin.manage.kpi.paid}}</span>
        <span class="adm-kpi__icon adm-kpi__icon--paid" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
      </div>
      <p class="adm-kpi__value" x-text="int(counts.U)">{{paid}}</p>
      <p class="adm-kpi__hint" x-text="money(counts.U * price)">{{paid_amount}}</p>
    </article>
    <article class="adm-kpi adm-kpi--mini" :class="counts.R > 0 && 'adm-kpi--pending'">
      <div class="adm-kpi__head">
        <span class="adm-kpi__label">{{t:admin.manage.kpi.pending}}</span>
        <span class="adm-kpi__icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
      </div>
      <p class="adm-kpi__value" x-text="money(counts.R * price)">{{reserved_amount}}</p>
      <p class="adm-kpi__hint" x-text="$tc('manage_pending_numbers', counts.R, { count: int(counts.R) })">{{reserved}}</p>
    </article>
    <article class="adm-kpi adm-kpi--mini">
      <div class="adm-kpi__head">
        <span class="adm-kpi__label">{{t:admin.manage.kpi.free}}</span>
        <span class="adm-kpi__icon" aria-hidden="true"><i class="bi bi-circle"></i></span>
      </div>
      <p class="adm-kpi__value" x-text="int(counts.D)">{{free}}</p>
      <p class="adm-kpi__hint" x-text="$t('manage_of_total', { total: int(total) })">{{total}}</p>
    </article>
    <article class="adm-kpi adm-kpi--mini">
      <div class="adm-kpi__head">
        <span class="adm-kpi__label">{{t:admin.manage.kpi.sold}}</span>
        <span class="adm-kpi__icon" aria-hidden="true"><i class="bi bi-pie-chart"></i></span>
      </div>
      <p class="adm-kpi__value"><span x-text="percent">{{percent}}</span>%</p>
      <span class="adm-stackbar adm-stackbar--mini" role="img" :aria-label="$t('manage_bar_label', { paid: int(counts.U), reserved: int(counts.R), free: int(counts.D) })">
        <span class="adm-stackbar__seg adm-stackbar__seg--paid" :style="`width: ${pct(counts.U)}%`"></span>
        <span class="adm-stackbar__seg adm-stackbar__seg--reserved" :style="`width: ${pct(counts.R)}%`"></span>
      </span>
    </article>
  </section>

  <div class="adm-card adm-manage__panel">
    <div class="adm-tabs" role="tablist" aria-label="{{t:admin.manage.tabs.label}}" @keydown.right.prevent="moveTab(1)" @keydown.left.prevent="moveTab(-1)">
      <button type="button" class="adm-tab" role="tab" id="adm-tab-buyers" aria-controls="adm-panel-buyers" :aria-selected="tab === 'buyers'" :tabindex="tab === 'buyers' ? 0 : -1" @click="setTab('buyers')">
        <i class="bi bi-people" aria-hidden="true"></i>{{t:admin.manage.tabs.buyers}}
        <span class="adm-chip__count" x-show="buyerCounts.pending > 0" x-text="int(buyerCounts.pending)" :title="$tc('manage_pending_buyers', buyerCounts.pending, { count: buyerCounts.pending })"></span>
      </button>
      <button type="button" class="adm-tab" role="tab" id="adm-tab-tickets" aria-controls="adm-panel-tickets" :aria-selected="tab === 'tickets'" :tabindex="tab === 'tickets' ? 0 : -1" @click="setTab('tickets')">
        <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>{{t:admin.manage.tabs.tickets}}
      </button>
      <button type="button" class="adm-tab" role="tab" id="adm-tab-history" aria-controls="adm-panel-history" :aria-selected="tab === 'history'" :tabindex="tab === 'history' ? 0 : -1" @click="setTab('history')">
        <i class="bi bi-clock-history" aria-hidden="true"></i>{{t:admin.manage.tabs.history}}
      </button>
    </div>

    <div class="adm-tabpanel" role="tabpanel" id="adm-panel-buyers" aria-labelledby="adm-tab-buyers" x-show="tab === 'buyers'" x-cloak>
      {{buyers}}
    </div>
    <div class="adm-tabpanel" role="tabpanel" id="adm-panel-tickets" aria-labelledby="adm-tab-tickets" x-show="tab === 'tickets'" x-cloak>
      {{tickets}}
    </div>
    <div class="adm-tabpanel" role="tabpanel" id="adm-panel-history" aria-labelledby="adm-tab-history" x-show="tab === 'history'" x-cloak>
      {{history}}
    </div>
  </div>

  <!-- Drawer: atribuir números livres a um comprador -->
  <div class="adm-drawer" x-cloak x-show="drawer" role="dialog" aria-modal="true" aria-labelledby="adm-assign-title">
    <div class="adm-dialog__backdrop" x-show="drawer" x-transition.opacity @click="closeDrawer()"></div>
    <form class="adm-drawer__panel" x-show="drawer" x-transition:enter="adm-drawer-enter" x-transition:enter-start="adm-drawer-from"
          x-transition:leave="adm-drawer-enter" x-transition:leave-end="adm-drawer-from" @submit.prevent="saveAssign()" novalidate>
      <header class="adm-drawer__head">
        <h2 class="adm-dialog__title" id="adm-assign-title">{{t:admin.manage.assign.title}}</h2>
        <button type="button" class="adm-icon-btn adm-icon-btn--sm" @click="closeDrawer()" aria-label="{{t:admin.raffles.dialog.close}}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
      </header>
      <div class="adm-drawer__body">
        <div class="adm-field">
          <span class="adm-label" x-text="$tc('manage_selected_numbers', picked.length, { count: int(picked.length) })"></span>
          <div class="adm-num-chips">
            <template x-for="n in picked.slice(0, 30)" :key="n"><span class="adm-num-chip adm-num-chip--picked" x-text="label(n)"></span></template>
            <span class="adm-num-chip adm-num-chip--more" x-show="picked.length > 30" x-text="'+' + (picked.length - 30)"></span>
          </div>
          <p class="adm-hint" x-text="$t('manage_assign_total', { amount: money(picked.length * price) })"></p>
        </div>
        <div class="adm-field">
          <label class="adm-label" for="adm-assign-phone">{{t:admin.manage.assign.phone}}</label>
          <input type="tel" id="adm-assign-phone" class="adm-input" x-model="form.phone" inputmode="tel" autocomplete="off" data-autofocus
                 x-mask:dynamic="form.phone.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-99999'"
                 @input.debounce.400ms="lookup()" :aria-invalid="formError === 'phone' ? 'true' : 'false'" aria-describedby="adm-assign-lookup">
          <p class="adm-hint" id="adm-assign-lookup" aria-live="polite">
            <span x-show="lookupState === 'loading'"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{t:admin.manage.assign.searching}}</span>
            <span x-show="lookupState === 'found'" class="adm-text-paid"><i class="bi bi-person-check" aria-hidden="true"></i> {{t:admin.manage.assign.found}}</span>
            <span x-show="lookupState === 'new'"><i class="bi bi-person-plus" aria-hidden="true"></i> {{t:admin.manage.assign.new}}</span>
          </p>
        </div>
        <div class="adm-field">
          <label class="adm-label" for="adm-assign-name">{{t:admin.manage.assign.name}}</label>
          <input type="text" id="adm-assign-name" class="adm-input" x-model="form.name" autocomplete="off" maxlength="255" :aria-invalid="formError === 'name' ? 'true' : 'false'">
        </div>
        <div class="adm-field">
          <label class="adm-label" for="adm-assign-email">{{t:admin.manage.assign.email}}</label>
          <input type="email" id="adm-assign-email" class="adm-input" x-model="form.email" autocomplete="off" maxlength="255">
        </div>
        <p class="adm-field__error" role="alert" x-show="formMessage" x-text="formMessage"></p>
      </div>
      <footer class="adm-drawer__foot">
        <button type="button" class="adm-btn adm-btn--secondary" @click="closeDrawer()">{{t:admin.raffles.dialog.cancel}}</button>
        <button type="submit" class="adm-btn adm-btn--primary" :class="saving && 'is-loading'" :disabled="saving"><i class="bi bi-check2" aria-hidden="true"></i>{{t:admin.manage.assign.save}}</button>
      </footer>
    </form>
  </div>

  <script type="application/json" id="adm-manage-data">{{data}}</script>
</div>
