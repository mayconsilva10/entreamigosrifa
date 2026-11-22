{{header}}
{{not_configured}}

<div class="adm-checkout" x-data="releaseCheckout">
  <script type="application/json" id="adm-checkout-data">{{data}}</script>

  <section class="adm-card adm-fsection adm-checkout__summary" aria-labelledby="adm-checkout-summary">
    <header class="adm-fsection__head">
      <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
      <div>
        <h2 class="adm-fsection__title" id="adm-checkout-summary">{{t:admin.checkout.summary.title}}</h2>
        <p class="adm-hint">{{t:admin.checkout.summary.hint}}</p>
      </div>
    </header>
    <dl class="adm-checkout__lines">
      <div><dt>{{t:admin.checkout.summary.raffle}}</dt><dd>{{title_raffle}}</dd></div>
      <div><dt>{{t:admin.checkout.summary.numbers}}</dt><dd class="adm-num">{{qtd_numbers}}</dd></div>
      <div><dt>{{t:admin.checkout.summary.fee}}</dt><dd class="adm-num">× {{fee}}</dd></div>
      <div class="adm-checkout__total"><dt>{{t:admin.checkout.summary.total}}</dt><dd class="adm-num">{{total_pay}}</dd></div>
    </dl>

    <div class="adm-checkout__status" aria-live="polite">
      <span class="adm-hint">{{t:admin.checkout.state.label}}</span>
      <span x-show="state === 'pending'">{{badge_pending}}</span>
      <span x-show="state === 'paid'" x-cloak>{{badge_paid}}</span>
      <span x-show="state === 'expired'" x-cloak>{{badge_expired}}</span>
    </div>
    <p class="adm-hint" x-show="state === 'pending' && polling" x-cloak><span class="adm-pulse" aria-hidden="true"></span>{{t:admin.checkout.state.polling}}</p>
    <p class="adm-hint" x-show="state === 'expired'" x-cloak>{{t:admin.checkout.state.expired_hint}}</p>
    <div class="adm-checkout__verify">
      <button type="button" class="adm-btn adm-btn--secondary" x-show="state !== 'expired'" @click="verify(true)" :class="checking && 'is-loading'" :aria-disabled="checking.toString()">
        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>{{t:admin.checkout.verify}}
      </button>
      <a class="adm-btn adm-btn--primary" href="" x-show="state === 'expired'" x-cloak><i class="bi bi-qr-code" aria-hidden="true"></i>{{t:admin.checkout.renew}}</a>
    </div>
  </section>

  <div class="adm-checkout__methods">
    <section class="adm-card adm-fsection" aria-labelledby="adm-checkout-pix" :class="state === 'expired' && 'is-expired'">
      <header class="adm-fsection__head">
        <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-qr-code"></i></span>
        <div>
          <h2 class="adm-fsection__title" id="adm-checkout-pix">{{t:admin.checkout.pix.title}}</h2>
          <p class="adm-hint">{{t:admin.checkout.pix.hint}}</p>
        </div>
      </header>
      {{pix}}
    </section>

    <section class="adm-card adm-fsection" aria-labelledby="adm-checkout-card">
      <header class="adm-fsection__head">
        <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-credit-card"></i></span>
        <div>
          <h2 class="adm-fsection__title" id="adm-checkout-card">{{t:admin.checkout.card.title}}</h2>
          <p class="adm-hint">{{t:admin.checkout.card.hint}}</p>
        </div>
      </header>
      {{checkout_pro}}
    </section>
  </div>
</div>
