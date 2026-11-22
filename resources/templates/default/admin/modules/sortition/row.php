<tr class="adm-rl__row" data-raffle-id="{{id}}" data-title="{{title}}" data-code="{{code}}" data-url="{{url}}">
  <td class="adm-rl__raffle">
    <div class="adm-rl__item">
      <img class="adm-rl__thumb" src="{{photo}}" alt="" width="40" height="40" loading="lazy" decoding="async">
      <div class="adm-rl__text">
        <a class="adm-rl__title" href="{{manage}}">{{title}}</a>
        <button type="button" class="adm-rl__link" x-copy="$el.closest('tr').dataset.url" aria-label="{{copy_label}}" title="{{copy_label}}">
          <i class="bi bi-link-45deg" aria-hidden="true"></i><span class="adm-rl__url" data-link-text>{{short_url}}</span><i class="bi bi-files" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </td>
  <td class="adm-rl__status" data-label="{{t:admin.raffles.table.status}}">{{badge}}</td>
  <td class="is-num" data-label="{{t:admin.raffles.table.price}}">{{price}}</td>
  <td class="adm-rl__sold" data-label="{{t:admin.raffles.table.sold}}">
    <span class="adm-num"><strong>{{sold}}</strong>/{{total}}</span>
    <span class="adm-stackbar adm-stackbar--mini" role="img" aria-label="{{bar_label}}">
      <span class="adm-stackbar__seg adm-stackbar__seg--paid" style="width: {{paid_pct}}%"></span>
      <span class="adm-stackbar__seg adm-stackbar__seg--reserved" style="width: {{reserved_pct}}%"></span>
    </span>
  </td>
  <td class="is-num" data-label="{{t:admin.raffles.table.pending}}">{{pending}}</td>
  <td class="adm-rl__draw" data-label="{{t:admin.raffles.table.draw}}">
    <span class="adm-num">{{draw_date}}</span>
    {{draw_relative}}
  </td>
  <td class="adm-rl__actions">
    <a class="adm-btn adm-btn--primary adm-btn--sm adm-rl__manage" href="{{manage}}"><i class="bi bi-sliders" aria-hidden="true"></i>{{t:admin.raffles.row.manage}}</a>
    <div class="dropdown">
      <button type="button" class="adm-icon-btn adm-rl__more" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{more_label}}" title="{{more_label}}">
        <i class="bi bi-three-dots" aria-hidden="true"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end adm-menu" data-preview-base="{{preview_base}}">
        {{menu}}
      </ul>
    </div>
  </td>
</tr>
