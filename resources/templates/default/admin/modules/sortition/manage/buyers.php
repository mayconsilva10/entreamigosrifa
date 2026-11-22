<div class="adm-toolbar" role="search">
  <div class="adm-toolbar__row">
    <div class="adm-toolbar__search">
      <label class="visually-hidden" for="adm-buyers-q">{{t:admin.manage.buyers.search_label}}</label>
      <i class="bi bi-search" aria-hidden="true"></i>
      <input type="search" id="adm-buyers-q" class="adm-input" x-model.debounce.250ms="query" placeholder="{{t:admin.manage.buyers.search_placeholder}}" autocomplete="off" maxlength="100">
    </div>
  </div>
  <div class="adm-toolbar__row">
    <div class="adm-chips" role="group" aria-label="{{t:admin.manage.buyers.filters}}">
      <button type="button" class="adm-chip" :class="filter === 'pending' && 'is-active'" :aria-pressed="filter === 'pending'" @click="filter = 'pending'">
        {{t:admin.manage.buyers.chip_pending}}<span class="adm-chip__count" x-text="int(buyerCounts.pending)"></span>
      </button>
      <button type="button" class="adm-chip" :class="filter === 'paid' && 'is-active'" :aria-pressed="filter === 'paid'" @click="filter = 'paid'">
        {{t:admin.manage.buyers.chip_paid}}<span class="adm-chip__count" x-text="int(buyerCounts.paid)"></span>
      </button>
      <button type="button" class="adm-chip" :class="filter === 'all' && 'is-active'" :aria-pressed="filter === 'all'" @click="filter = 'all'">
        {{t:admin.manage.buyers.chip_all}}<span class="adm-chip__count" x-text="int(buyerCounts.all)"></span>
      </button>
    </div>
  </div>
</div>

<p class="visually-hidden" aria-live="polite" x-text="$tc('manage_buyers_found', shown.length, { count: shown.length })"></p>

<table class="adm-table adm-bl" x-show="shown.length > 0">
  <caption class="visually-hidden">{{t:admin.manage.buyers.caption}}</caption>
  <thead>
    <tr>
      <th scope="col" class="adm-bl__check">
        <input type="checkbox" class="adm-check" :checked="allVisibleSelected" :indeterminate="someVisibleSelected" :disabled="selectableVisible.length === 0"
               @change="toggleAllVisible($event.target.checked)" aria-label="{{t:admin.manage.buyers.select_all}}">
      </th>
      <th scope="col">{{t:admin.manage.buyers.buyer}}</th>
      <th scope="col">{{t:admin.manage.buyers.numbers}}</th>
      <th scope="col" class="is-num">{{t:admin.manage.buyers.amount}}</th>
      <th scope="col">{{t:admin.manage.buyers.status}}</th>
      <th scope="col">{{t:admin.manage.buyers.reserved_at}}</th>
      <th scope="col"><span class="visually-hidden">{{t:admin.manage.buyers.actions}}</span></th>
    </tr>
  </thead>
  <template x-for="b in shown.slice(0, limit)" :key="b.id">
    <tbody class="adm-bl__group" :class="[updated.includes(b.id) && 'is-updated', isSelected(b.id) && 'is-selected']">
      <tr class="adm-bl__row">
        <td class="adm-bl__check">
          <input type="checkbox" class="adm-check" :checked="isSelected(b.id)" :disabled="b.reserved.length === 0" @change="toggleSelect(b.id)"
                 :aria-label="$t('manage_select_buyer', { name: b.name })">
        </td>
        <td class="adm-bl__buyer">
          <button type="button" class="adm-bl__name" @click="toggleOpen(b.id)" :aria-expanded="isOpen(b.id)" :aria-controls="'adm-buyer-' + b.id">
            <i class="bi bi-chevron-right adm-bl__caret" aria-hidden="true"></i><span x-text="b.name"></span>
          </button>
          <span class="adm-bl__phone">
            <span class="adm-num" x-text="b.phone || '—'"></span>
            <a class="adm-btn adm-btn--whatsapp adm-btn--xs" x-show="b.whatsapp && b.reserved.length > 0" :href="chargeUrl(b)" target="_blank" rel="noopener"
               :aria-label="$t('manage_charge_label', { name: b.name })"><i class="bi bi-whatsapp" aria-hidden="true"></i>{{t:admin.manage.buyers.charge}}</a>
            <a class="adm-icon-btn adm-icon-btn--sm" x-show="b.whatsapp && b.reserved.length === 0" :href="'https://wa.me/' + b.whatsapp" target="_blank" rel="noopener"
               :aria-label="$t('manage_whatsapp_label', { name: b.name })"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>
          </span>
        </td>
        <td class="adm-bl__numbers" data-label="{{t:admin.manage.buyers.numbers}}">
          <span class="adm-bl__qty adm-num" x-text="$tc('manage_numbers_count', b.paid.length + b.reserved.length, { count: int(b.paid.length + b.reserved.length) })"></span>
          <span class="adm-num-chips">
            <template x-for="n in b._chips" :key="n"><span class="adm-num-chip" :class="b._pend[n] ? 'adm-num-chip--pending' : 'adm-num-chip--paid'" x-text="label(n)"></span></template>
            <button type="button" class="adm-num-chip adm-num-chip--more" x-show="b._more > 0" x-text="'+' + int(b._more)" @click="toggleOpen(b.id, true)"
                    :aria-label="$tc('manage_more_numbers', b._more, { count: b._more })"></button>
          </span>
        </td>
        <td class="is-num" data-label="{{t:admin.manage.buyers.amount}}">
          <span class="adm-num" x-text="money(b.amount)"></span>
          <span class="adm-rl__sub adm-text-pending" x-show="b.pending_amount > 0 && b.status === 'partial'" x-text="$t('manage_pending_amount', { amount: money(b.pending_amount) })"></span>
        </td>
        <td data-label="{{t:admin.manage.buyers.status}}">
          <span class="adm-badge" :class="'adm-badge--' + (b.status === 'paid' ? 'paid' : 'pending')" :data-status="b.status">
            <i class="bi" :class="b.status === 'paid' ? 'bi-check-circle-fill' : (b.status === 'partial' ? 'bi-circle-half' : 'bi-hourglass-split')" aria-hidden="true"></i>
            <span x-text="$t('manage_status_' + b.status)"></span>
          </span>
        </td>
        <td data-label="{{t:admin.manage.buyers.reserved_at}}">
          <template x-if="b.reserved_at">
            <time class="adm-bl__ago" :class="isLate(b) && 'is-late'" :datetime="b.reserved_at" :title="dateTime(b.reserved_at)">
              <i class="bi bi-clock" aria-hidden="true" x-show="isLate(b)"></i><span x-text="ago(b.reserved_at)"></span>
            </time>
          </template>
          <span class="adm-rl__none" x-show="!b.reserved_at">—</span>
        </td>
        <td class="adm-bl__actions" :class="b.reserved.length === 0 && 'is-empty'">
          <button type="button" class="adm-btn adm-btn--primary adm-btn--sm" x-show="b.reserved.length > 0" @click="act('confirm', [b.id])" :disabled="busy">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>{{t:admin.manage.buyers.confirm}}
          </button>
          <button type="button" class="adm-btn adm-btn--ghost adm-btn--sm adm-bl__cancel" x-show="b.reserved.length > 0" @click="act('cancel', [b.id])" :disabled="busy"
                  :aria-label="$t('manage_cancel_label', { name: b.name })" :title="$t('manage_cancel_label', { name: b.name })">
            <i class="bi bi-x-circle" aria-hidden="true"></i><span class="adm-bl__cancel-text">{{t:admin.manage.buyers.cancel}}</span>
          </button>
        </td>
      </tr>
      <tr class="adm-bl__detail" :class="isOpen(b.id) && 'is-open'">
        <td colspan="7">
          <div :id="'adm-buyer-' + b.id" x-show="isOpen(b.id)" x-collapse>
            <template x-if="wasOpened(b.id)">
              <div class="adm-bl__detail-body">
                <p class="adm-bl__contact" x-show="b.email"><i class="bi bi-envelope" aria-hidden="true"></i><span x-text="b.email"></span></p>
                <template x-for="(g, i) in b.groups" :key="i">
                  <div class="adm-bl__batch">
                    <p class="adm-bl__batch-head">
                      <span class="adm-badge" :class="g.status === 'paid' ? 'adm-badge--paid' : 'adm-badge--pending'">
                        <i class="bi" :class="g.status === 'paid' ? 'bi-check-circle-fill' : 'bi-hourglass-split'" aria-hidden="true"></i><span x-text="$t('manage_status_' + g.status)"></span>
                      </span>
                      <span class="adm-num" x-text="g.at ? $t('manage_reserved_on', { date: dateTime(g.at) }) : $t('manage_no_date')"></span>
                    </p>
                    <div class="adm-num-chips">
                      <template x-for="n in g.numbers" :key="n"><span class="adm-num-chip" :class="g.status === 'paid' ? 'adm-num-chip--paid' : 'adm-num-chip--pending'" x-text="label(n)"></span></template>
                    </div>
                  </div>
                </template>
              </div>
            </template>
          </div>
        </td>
      </tr>
    </tbody>
  </template>
</table>

<div class="adm-bl__more" x-show="shown.length > limit">
  <button type="button" class="adm-btn adm-btn--secondary" @click="limit += 50" x-text="$t('manage_show_more', { shown: int(Math.min(limit, shown.length)), total: int(shown.length) })"></button>
</div>

<div class="adm-empty" role="status" x-show="shown.length === 0">
  <span class="adm-empty__icon" aria-hidden="true"><i class="bi" :class="buyers.length === 0 ? 'bi-people' : (query ? 'bi-search' : 'bi-check2-all')"></i></span>
  <h2 class="adm-empty__title" x-text="buyers.length === 0 ? $t('manage_empty_none_title') : (query ? $t('manage_empty_search_title') : $t('manage_empty_filter_title'))"></h2>
  <p class="adm-empty__text" x-text="buyers.length === 0 ? $t('manage_empty_none_text') : (query ? $t('manage_empty_search_text', { q: query }) : $t('manage_empty_filter_text'))"></p>
  <button type="button" class="adm-btn adm-btn--secondary" x-show="buyers.length > 0" @click="query = ''; filter = 'all'"><i class="bi bi-x-circle" aria-hidden="true"></i>{{t:admin.raffles.toolbar.clear}}</button>
  <button type="button" class="adm-btn adm-btn--primary" x-show="buyers.length === 0" x-copy="shareUrl"><i class="bi bi-files" aria-hidden="true"></i>{{t:admin.manage.buyers.copy_link}}</button>
</div>

<div class="adm-bulkbar" x-cloak x-show="selected.length > 0" x-transition.opacity role="region" aria-label="{{t:admin.manage.buyers.bulk_label}}">
  <p class="adm-bulkbar__text" aria-live="polite">
    <strong x-text="$tc('manage_selected', selected.length, { count: int(selected.length) })"></strong>
    <span x-text="'· ' + money(selectedAmount)"></span>
  </p>
  <div class="adm-bulkbar__actions">
    <button type="button" class="adm-btn adm-btn--ghost adm-btn--sm" @click="selected = []">{{t:admin.manage.buyers.clear_selection}}</button>
    <button type="button" class="adm-btn adm-btn--danger adm-btn--sm" @click="act('cancel', [...selected])" :disabled="busy"><i class="bi bi-x-circle" aria-hidden="true"></i>{{t:admin.manage.buyers.bulk_cancel}}</button>
    <button type="button" class="adm-btn adm-btn--primary adm-btn--sm" :class="busy && 'is-loading'" @click="act('confirm', [...selected])" :disabled="busy"><i class="bi bi-check2-circle" aria-hidden="true"></i>{{t:admin.manage.buyers.bulk_confirm}}</button>
  </div>
</div>
