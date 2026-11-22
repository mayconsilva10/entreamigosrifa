<li class="adm-raffle-card">
  <img class="adm-raffle-card__thumb" src="{{photo}}" alt="" width="56" height="56" loading="lazy" decoding="async">
  <div class="adm-raffle-card__body">
    <div class="adm-raffle-card__top">
      <a class="adm-raffle-card__title" href="{{manage}}">{{title}}</a>
      {{badge}}
    </div>
    <div class="adm-stackbar" role="img" aria-label="{{bar_label}}">
      <span class="adm-stackbar__seg adm-stackbar__seg--paid" style="width: {{paid_pct}}%"></span>
      <span class="adm-stackbar__seg adm-stackbar__seg--reserved" style="width: {{reserved_pct}}%"></span>
    </div>
    <div class="adm-raffle-card__meta">
      <span class="adm-num"><strong>{{sold}}</strong> · {{percent}}% · {{amount}}</span>
      <span><i class="bi bi-calendar-event" aria-hidden="true"></i> {{draw}}</span>
    </div>
  </div>
  <div class="adm-raffle-card__actions">
    <a class="adm-btn adm-btn--secondary adm-btn--sm" href="{{manage}}"><i class="bi bi-sliders" aria-hidden="true"></i>{{t:admin.dashboard.progress.manage}}</a>
    {{secondary}}
  </div>
</li>
