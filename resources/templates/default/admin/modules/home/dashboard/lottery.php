<section class="adm-card adm-lottery" x-data="{ open: window.matchMedia('(min-width: 992px)').matches }">
  <header class="adm-section-head">
    <h2 class="adm-section-head__title" id="adm-lottery-title"><i class="bi bi-ticket-detailed" aria-hidden="true"></i>{{t:admin.dashboard.lottery.title}}</h2>
    <button type="button" class="adm-icon-btn adm-lottery__toggle" @click="open = !open" :aria-expanded="open.toString()" aria-controls="adm-lottery-body" aria-label="{{t:admin.dashboard.lottery.toggle}}">
      <i class="bi bi-chevron-down" :class="open && 'is-open'" aria-hidden="true"></i>
    </button>
  </header>
  <dl class="adm-lottery__facts">
    <div><dt>{{t:admin.dashboard.lottery.last}}</dt><dd class="adm-num">{{contest}}</dd></div>
    <div><dt>{{t:admin.dashboard.lottery.date}}</dt><dd class="adm-num">{{date}}</dd></div>
    <div><dt>{{t:admin.dashboard.lottery.next}}</dt><dd class="adm-num">{{next}}</dd></div>
  </dl>
  <div class="adm-lottery__body" id="adm-lottery-body" x-show="open" x-collapse>
    {{rows}}
    <p class="adm-lottery__source">
      <a href="https://loterias.caixa.gov.br/Paginas/Federal.aspx" target="_blank" rel="noopener">{{t:admin.dashboard.lottery.source}}<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
    </p>
  </div>
</section>
