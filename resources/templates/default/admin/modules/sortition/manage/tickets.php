<div class="adm-toolbar">
  <div class="adm-toolbar__row">
    <form class="adm-toolbar__search" role="search" @submit.prevent="jumpTo()">
      <label class="visually-hidden" for="adm-tickets-q">{{t:admin.manage.tickets.search_label}}</label>
      <i class="bi bi-search" aria-hidden="true"></i>
      <input type="search" id="adm-tickets-q" class="adm-input" x-model="numberQuery" inputmode="numeric" placeholder="{{t:admin.manage.tickets.search_placeholder}}" autocomplete="off" maxlength="7">
      <button type="submit" class="visually-hidden">{{t:admin.manage.tickets.search_submit}}</button>
    </form>
    <div class="adm-toolbar__sort" x-show="pages.length > 1">
      <label class="adm-toolbar__label" for="adm-tickets-range">{{t:admin.manage.tickets.range}}</label>
      <button type="button" class="adm-icon-btn adm-icon-btn--sm" @click="goPage(page - 1)" :disabled="page === 0" aria-label="{{t:admin.pagination.previous}}"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
      <select id="adm-tickets-range" class="adm-input adm-select" :value="page" @change="goPage(Number($event.target.value))">
        <template x-for="(p, i) in pages" :key="i"><option :value="i" x-text="p" :selected="i === page"></option></template>
      </select>
      <button type="button" class="adm-icon-btn adm-icon-btn--sm" @click="goPage(page + 1)" :disabled="page >= pages.length - 1" aria-label="{{t:admin.pagination.next}}"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
    </div>
  </div>
  <div class="adm-toolbar__row">
    <div class="adm-chips" role="group" aria-label="{{t:admin.manage.tickets.filters}}">
      <template x-for="f in ticketFilters" :key="f.key">
        <button type="button" class="adm-chip" :class="tFilter === f.key && 'is-active'" :aria-pressed="tFilter === f.key" @click="setTicketFilter(f.key)">
          <i class="bi" :class="f.icon" aria-hidden="true"></i><span x-text="f.label"></span><span class="adm-chip__count" x-text="int(f.count)"></span>
        </button>
      </template>
    </div>
  </div>
</div>

<ul class="adm-legend adm-tickets__legend" aria-label="{{t:admin.manage.tickets.legend}}">
  <li><span class="adm-tk adm-tk--sample is-free" aria-hidden="true"></span>{{t:admin.status.available}}</li>
  <li><span class="adm-tk adm-tk--sample is-pending" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>{{t:admin.status.pending}}</li>
  <li><span class="adm-tk adm-tk--sample is-paid" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>{{t:admin.status.paid}}</li>
  <li><span class="adm-tk adm-tk--sample is-picked" aria-hidden="true"><i class="bi bi-plus-circle-fill"></i></span>{{t:admin.manage.tickets.picked}}</li>
  <li x-show="winners.length > 0"><span class="adm-tk adm-tk--sample is-winner" aria-hidden="true"><i class="bi bi-trophy-fill"></i></span>{{t:admin.status.winner}}</li>
</ul>
<p class="adm-hint adm-tickets__hint">{{t:admin.manage.tickets.hint}}</p>

<div class="adm-tickets" @click.outside="popover = null">
  <div class="adm-tickets__grid" role="group" aria-label="{{t:admin.manage.tickets.grid_label}}" x-show="pageNumbers.length > 0">
    <template x-for="n in pageNumbers" :key="n">
      <button type="button" class="adm-tk" :class="cellClass(n)" :data-number="n" @click="clickCell(n, $event)" :aria-pressed="statusOf(n) === 'D' ? isPicked(n) : null"
              :aria-label="cellLabel(n)" :aria-haspopup="statusOf(n) === 'D' ? null : 'dialog'">
        <span x-text="label(n)"></span><i class="bi adm-tk__icon" :class="cellIcon(n)" aria-hidden="true"></i>
      </button>
    </template>
  </div>

  <div class="adm-empty" role="status" x-show="pageNumbers.length === 0">
    <span class="adm-empty__icon" aria-hidden="true"><i class="bi bi-grid-3x3-gap"></i></span>
    <h2 class="adm-empty__title">{{t:admin.manage.tickets.empty_title}}</h2>
    <p class="adm-empty__text">{{t:admin.manage.tickets.empty_text}}</p>
  </div>

  <!-- Popover do número ocupado -->
  <div class="adm-popover" x-cloak x-show="popover" x-transition.opacity role="dialog" aria-labelledby="adm-popover-title" :style="popover && `left: ${popover.x}px; top: ${popover.y}px`">
    <template x-if="popover">
      <div>
        <p class="adm-popover__number" id="adm-popover-title" x-text="$t('manage_number', { number: label(popover.n) })"></p>
        <p class="adm-popover__name" x-text="popover.buyer ? popover.buyer.name : '—'"></p>
        <p class="adm-num adm-popover__phone" x-show="popover.buyer && popover.buyer.phone" x-text="popover.buyer && popover.buyer.phone"></p>
        <span class="adm-badge" :class="statusOf(popover.n) === 'U' ? 'adm-badge--paid' : 'adm-badge--pending'">
          <i class="bi" :class="statusOf(popover.n) === 'U' ? 'bi-check-circle-fill' : 'bi-hourglass-split'" aria-hidden="true"></i>
          <span x-text="$t(statusOf(popover.n) === 'U' ? 'manage_status_paid' : 'manage_status_pending')"></span>
        </span>
        <div class="adm-popover__actions">
          <button type="button" class="adm-btn adm-btn--secondary adm-btn--sm" x-show="popover.buyer" @click="showBuyer(popover.buyer)" data-popover-focus>
            <i class="bi bi-person" aria-hidden="true"></i>{{t:admin.manage.tickets.view_buyer}}
          </button>
          <a class="adm-btn adm-btn--whatsapp adm-btn--sm" x-show="popover.buyer && popover.buyer.whatsapp" :href="popover.buyer && popover.buyer.reserved.length ? chargeUrl(popover.buyer) : 'https://wa.me/' + (popover.buyer ? popover.buyer.whatsapp : '')"
             target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i>WhatsApp</a>
        </div>
      </div>
    </template>
  </div>
</div>

<div class="adm-bulkbar" x-cloak x-show="picked.length > 0" x-transition.opacity role="region" aria-label="{{t:admin.manage.tickets.bulk_label}}">
  <p class="adm-bulkbar__text" aria-live="polite">
    <strong x-text="$tc('manage_selected_numbers', picked.length, { count: int(picked.length) })"></strong>
    <span x-text="'· ' + money(picked.length * price)"></span>
  </p>
  <div class="adm-bulkbar__actions">
    <button type="button" class="adm-btn adm-btn--ghost adm-btn--sm" @click="picked = []">{{t:admin.manage.buyers.clear_selection}}</button>
    <button type="button" class="adm-btn adm-btn--primary adm-btn--sm" @click="openDrawer()"><i class="bi bi-person-plus" aria-hidden="true"></i>{{t:admin.manage.tickets.assign}}</button>
  </div>
</div>
