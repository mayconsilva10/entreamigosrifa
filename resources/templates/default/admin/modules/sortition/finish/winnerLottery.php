{{header}}
{{flash}}

<div class="adm-winners" x-data="winners">
  <script type="application/json" id="adm-winners-data">{{data}}</script>

  <section class="adm-card adm-fsection adm-winners__draw" aria-labelledby="adm-draw-title">
    <header class="adm-fsection__head">
      <span class="adm-fsection__icon" aria-hidden="true"><i class="bi bi-stars"></i></span>
      <div>
        <h2 class="adm-fsection__title" id="adm-draw-title">{{t:admin.winners.draw.title}}</h2>
        <p class="adm-hint">{{t:admin.winners.draw.subtitle}}</p>
      </div>
    </header>

    <ol class="adm-steps">
      <li class="adm-step">
        <span class="adm-step__num" aria-hidden="true">1</span>
        <div class="adm-step__body">
          <h3 class="adm-step__title">{{t:admin.winners.lottery.title}}</h3>
          <p class="adm-hint">{{t:admin.winners.lottery.contest}} <span class="adm-num">{{lottery_contest}}</span> · <span class="adm-num">{{lottery_date}}</span></p>
          {{prizes}}
          <p class="adm-lottery__source">
            <a href="https://loterias.caixa.gov.br/Paginas/Federal.aspx" target="_blank" rel="noopener">{{t:admin.dashboard.lottery.source}}<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
          </p>
        </div>
      </li>

      <li class="adm-step">
        <span class="adm-step__num" aria-hidden="true">2</span>
        <form class="adm-step__body" method="post" action="{{add_url}}" data-adm-submit @submit="submit($event)">
          <h3 class="adm-step__title"><label for="adm-winner-number">{{t:admin.winners.number.title}}</label></h3>
          <div class="adm-winners__lookup">
            <input type="number" class="adm-input adm-num" id="adm-winner-number" name="number" min="1" max="{{total}}" inputmode="numeric" required
                   placeholder="{{t:admin.winners.number.placeholder}}" aria-describedby="adm-winner-number-hint" x-model.number="number" x-ref="number" @input="reset()">
            <button type="button" class="adm-btn adm-btn--secondary" @click="check()" :class="loading && 'is-loading'" :aria-disabled="loading.toString()">
              <i class="bi bi-search" aria-hidden="true"></i>{{t:admin.winners.number.check}}
            </button>
          </div>
          <p class="adm-hint" id="adm-winner-number-hint">{{number_hint}}</p>

          <div class="adm-step adm-step--inner" x-show="preview" x-cloak>
            <span class="adm-step__num" aria-hidden="true">3</span>
            <div class="adm-step__body" aria-live="polite">
              <h3 class="adm-step__title">{{t:admin.winners.preview.title}}</h3>
              <template x-if="preview && preview.state === 'buyer'">
                <div class="adm-winner adm-winner--preview">
                  <span class="adm-winner__num adm-num" x-text="preview.label"></span>
                  <div class="adm-winner__body">
                    <span class="adm-badge adm-badge--winner"><i class="bi bi-trophy-fill" aria-hidden="true"></i>{{t:admin.status.winner}}</span>
                    <span class="adm-badge" :class="'adm-badge--' + preview.status"><i class="bi" :class="preview.status === 'paid' ? 'bi-check-circle-fill' : 'bi-hourglass-split'" aria-hidden="true"></i><span x-text="preview.status_label"></span></span>
                    <strong class="adm-winner__name" x-text="preview.name"></strong>
                    <p class="adm-winner__meta" x-show="preview.phone"><span><i class="bi bi-telephone" aria-hidden="true"></i><span x-text="preview.phone"></span></span></p>
                  </div>
                </div>
              </template>
              <template x-if="preview && preview.state === 'none'">
                <div class="adm-winner adm-winner--preview">
                  <span class="adm-winner__num adm-num" x-text="preview.label"></span>
                  <div class="adm-winner__body">
                    <span class="adm-badge adm-badge--available"><i class="bi bi-circle" aria-hidden="true"></i>{{t:admin.winners.no_buyer}}</span>
                  </div>
                </div>
              </template>
              <p class="adm-hint adm-winners__note" x-show="preview && preview.message" x-text="preview && preview.message"></p>
            </div>
          </div>

          <p class="adm-field__error" role="alert" x-show="error" x-cloak><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span x-text="error"></span></p>

          <div class="adm-step adm-step--inner" x-show="preview" x-cloak>
            <span class="adm-step__num" aria-hidden="true">4</span>
            <div class="adm-step__body">
              <h3 class="adm-step__title">{{t:admin.winners.confirm.title}}</h3>
              <p class="adm-hint">{{t:admin.winners.confirm.hint}}</p>
              <div class="adm-winners__confirm">
                <button type="submit" class="adm-btn adm-btn--primary"><i class="bi bi-trophy" aria-hidden="true"></i>{{t:admin.winners.confirm.button}}</button>
                <button type="button" class="adm-btn adm-btn--ghost" @click="clear()">{{t:admin.winners.confirm.other}}</button>
              </div>
            </div>
          </div>
        </form>
      </li>
    </ol>
  </section>

  <section class="adm-card adm-winners__registered" aria-labelledby="adm-winners-title">
    <header class="adm-section-head">
      <h2 class="adm-section-head__title" id="adm-winners-title"><i class="bi bi-trophy" aria-hidden="true"></i>{{t:admin.winners.list.title}}</h2>
      <span class="adm-chip__count">{{count}}</span>
    </header>
    {{winner_list}}
    {{finish}}
  </section>
</div>
