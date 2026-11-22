<div class="rf-page">
    {{message}}

    <!-- Hero: galeria + informações essenciais -->
    <section class="rf-hero" aria-labelledby="rf-title">
        <div class="rf-hero__media">
            <div class="swiper rf-hero__swiper">
                <div class="swiper-wrapper">
                    {{slides}}
                </div>
                <div class="swiper-pagination"></div>
            </div>
            <span class="rf-hero__zoom"><i class="bi bi-zoom-in" aria-hidden="true"></i> {{t:raffle.tap_to_zoom}}</span>
            {{LOCALE_SWITCHER}}
        </div>

        <div class="rf-hero__info">
            <h1 id="rf-title" class="rf-hero__title">{{titleSortition}}</h1>

            <div class="rf-hero__price">
                <span class="rf-hero__price-value">{{price}}</span>
                <span class="rf-hero__price-unit">{{t:raffle.per_number}}</span>
            </div>

            <!-- Dados-chave: quando é o sorteio e quantos números existem -->
            <dl class="rf-stats">
                <div class="rf-stat">
                    <dt><i class="bi bi-calendar-event" aria-hidden="true"></i> {{t:raffle.stat_draw}}</dt>
                    <dd class="rf-stat__value">{{date_lottery}}</dd>
                    <dd class="rf-stat__sub"><span class="rf-badge">{{date_countdown}}</span></dd>
                </div>
                <div class="rf-stat">
                    <dt><i class="bi bi-ticket-detailed" aria-hidden="true"></i> {{t:raffle.stat_numbers}}</dt>
                    <dd class="rf-stat__value">{{qtdNumbers}}</dd>
                    <dd class="rf-stat__sub">{{t:raffle.stat_available}}</dd>
                </div>
            </dl>

            <div class="rf-progress-wrap">
                <div class="rf-progress" role="progressbar" aria-label="{{t:raffle.sold_numbers_aria}}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{soldPercent}}">
                    <div class="rf-progress__bar" style="width: {{soldPercent}}%"></div>
                </div>
                <p class="rf-progress__label">{{t!:raffle.sold_summary}}</p>
            </div>

            <div class="rf-hero__actions">
                <a class="rf-btn rf-btn--cta rf-hero__cta" href="#numeros">
                    <i class="bi bi-hand-index-thumb" aria-hidden="true"></i> {{t:raffle.cta_pick}}
                </a>
                <button type="button" class="rf-btn rf-btn--outline rf-hero__share" onclick="shareRaffle(this)" aria-label="{{t:raffle.share}}">
                    <i class="bi bi-share" aria-hidden="true"></i> <span>{{t:raffle.share_short}}</span>
                </button>
            </div>

            <!-- Selos de confiança (compactos, dentro do hero) -->
            <ul class="rf-trust__list" aria-label="{{t:raffle.how_it_works}}">
                <li><i class="bi bi-lightning-charge" aria-hidden="true"></i> {{t:raffle.trust_pix}}</li>
                <li><i class="bi bi-ticket" aria-hidden="true"></i> {{t:raffle.trust_instant}}</li>
                <li><i class="bi bi-bell" aria-hidden="true"></i> {{t:raffle.trust_notified}}</li>
            </ul>
        </div>

        <!-- Rodapé do hero: quem organiza a rifa -->
        <footer class="rf-owner">
            <img src="{{imageProfileProprietary}}" alt="" class="rf-owner__photo" width="44" height="44" loading="lazy">
            <div class="rf-owner__text">
                <small>{{t:raffle.organized_by}}</small>
                <strong>{{nameProprietary}}</strong>
            </div>
            <div class="rf-owner__social social">{{pageSocial}}</div>
            <a class="rf-btn rf-btn--ghost rf-owner__whats" href="https://wa.me/{{phone_whatsapp}}?text={{t:raffle.whatsapp_owner_message}}" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp" aria-hidden="true"></i> {{t:raffle.talk}}
            </a>
        </footer>
    </section>

    <!-- Seletor de números -->
    <section class="rf-picker" id="numeros" x-data="raffleGrid" aria-labelledby="rf-picker-title">
        <div class="rf-picker__head">
            <div>
                <h2 id="rf-picker-title">{{t:raffle.pick_title}}</h2>
                <p class="rf-picker__count" aria-live="polite">{{t!:raffle.available_summary}}</p>
            </div>
            <a href="#" class="rf-link" @click.prevent="$dispatch('rf-checkout', 'lookup')">
                <i class="bi bi-search" aria-hidden="true"></i> {{t:raffle.lookup_link}}
            </a>
        </div>

        <!-- Dois modos em abas: só um aparece por vez -->
        <div class="rf-tabs" role="tablist" aria-label="{{t:raffle.pick_modes_aria}}">
            <button type="button" role="tab" id="rf-tab-pick" class="rf-tab" :class="{ 'is-active': mode === 'pick' }" :aria-selected="mode === 'pick'" aria-controls="rf-panel-pick" @click="mode = 'pick'">
                <span aria-hidden="true">👆</span> {{t:raffle.tab_pick}}
            </button>
            <button type="button" role="tab" id="rf-tab-lucky" class="rf-tab rf-tab--lucky" :class="{ 'is-active': mode === 'lucky' }" :aria-selected="mode === 'lucky'" aria-controls="rf-panel-lucky" @click="mode = 'lucky'">
                <span aria-hidden="true">🎲</span> {{t:raffle.tab_lucky}}
            </button>
        </div>

        <!-- Modo Surpresinha (o sistema escolhe) -->
        <div id="rf-panel-lucky" class="rf-panel rf-panel--lucky" role="tabpanel" aria-labelledby="rf-tab-lucky" x-show="mode === 'lucky'" x-cloak>
            <p class="rf-panel__hint">{{t:raffle.lucky_hint}}</p>
            <template x-if="available > 0">
                <div>
                    <div class="rf-packs" role="radiogroup" aria-label="{{t:raffle_js.packs_aria}}">
                        <template x-for="size in packs" :key="size">
                            <button type="button" role="radio" class="rf-pack" :class="{ 'is-active': qty === size }" :aria-checked="qty === size" @click="qty = size">
                                <span class="rf-pack__tag" x-show="size === popularPack" x-text="$t('pack_popular')"></span>
                                <strong x-text="$tc('numbers_count', size)"></strong>
                                <span class="rf-pack__price" x-text="price(size)"></span>
                            </button>
                        </template>
                    </div>
                    <div class="rf-stepper">
                        <button type="button" class="rf-stepper__btn" @click="setQty(qty - 1)" :disabled="qty <= 1" aria-label="{{t:raffle_js.qty_less}}"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                        <label for="rf-qty" class="rf-sr-only">{{t:raffle_js.qty_label}}</label>
                        <input id="rf-qty" type="number" inputmode="numeric" min="1" :max="available" class="rf-input rf-stepper__input" :value="qty" @change="setQty($event.target.value); $event.target.value = qty">
                        <button type="button" class="rf-stepper__btn" @click="setQty(qty + 1)" :disabled="qty >= available" aria-label="{{t:raffle_js.qty_more}}"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                    </div>
                    <button type="button" class="rf-btn rf-btn--block rf-lucky__go" @click="addRandom(qty)">
                        <span aria-hidden="true">🎲</span> <span x-text="$tc('lucky_go', qty, { total: price(qty) })"></span>
                    </button>
                </div>
            </template>
            <p class="rf-panel__hint" x-show="available === 0" x-cloak><strong>{{t:raffle_js.random_none}}</strong></p>
        </div>

        <!-- Modo escolha livre -->
        <div id="rf-panel-pick" class="rf-panel" role="tabpanel" aria-labelledby="rf-tab-pick" x-show="mode === 'pick'">
            <div class="rf-toolbar">
                <form class="rf-search" @submit.prevent="search()" role="search">
                    <label for="rf-search-input" class="rf-sr-only">{{t:raffle.search_number}}</label>
                    <i class="bi bi-search rf-search__icon" aria-hidden="true"></i>
                    <input id="rf-search-input" type="number" inputmode="numeric" min="1" :max="total" x-model="query" @input.debounce.600ms="query ? search() : (searchMessage = '')" enterkeyhint="search" placeholder="{{t:raffle.search_number}}" class="rf-input">
                </form>

                <div class="rf-nav" x-ref="nav" x-show="!onlyFree && bands.length > 1">
                    <button type="button" class="rf-icon-btn rf-nav__arrow" @click="band--" :disabled="band === 0" aria-label="{{t:raffle.band_previous}}"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                    <label for="rf-band-select" class="rf-sr-only">{{t:raffle.band_select}}</label>
                    <select id="rf-band-select" class="rf-nav__select" x-model.number="band">
                        <template x-for="item in bands" :key="item.index">
                            <option :value="item.index" :selected="band === item.index" x-text="item.label + bandNote(item)"></option>
                        </template>
                    </select>
                    <button type="button" class="rf-icon-btn rf-nav__arrow" @click="band++" :disabled="band >= bands.length - 1" aria-label="{{t:raffle.band_next}}"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                </div>
            </div>
            <p class="rf-search__msg" x-show="searchMessage" x-cloak role="status" aria-live="polite">
                <span x-text="searchMessage"></span>
                <button type="button" class="rf-link" x-show="suggestion" @click="takeSuggestion()">{{t:raffle.take_suggestion}} <span x-text="suggestion ? label(suggestion) : ''"></span></button>
            </p>
            <p class="rf-nav__low" x-show="lowNote" x-cloak x-text="lowNote"></p>

            <div class="rf-gridbar">
                <button type="button" class="rf-switch" role="switch" :aria-checked="onlyFree" @click="onlyFree = !onlyFree">
                    <span class="rf-switch__track" aria-hidden="true"><span class="rf-switch__thumb"></span></span>
                    <span>{{t:raffle.only_free}} <small class="rf-switch__count" x-text="'(' + freeNumbers.length + ')'"></small></span>
                </button>
                <ul class="rf-legend" aria-label="{{t:raffle.legend}}" x-show="!onlyFree">
                    <li><span class="rf-num rf-num--R" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span> {{t:raffle.legend_reserved}}</li>
                    <li><span class="rf-num rf-num--U" aria-hidden="true"><i class="bi bi-lock-fill"></i></span> {{t:raffle.legend_paid}}</li>
                </ul>
            </div>

            <div class="rf-grid rf-grid--skeleton" x-show="!ready" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
            </div>
            <div class="rf-grid" x-ref="grid" role="group" aria-label="{{t:raffle.grid_aria}}">
                <template x-for="n in visible" :key="n">
                    <button type="button" class="rf-num" :id="'rf-n-' + n"
                        :class="['rf-num--' + state(n), { 'is-highlight': highlight === n }]"
                        :aria-label="ariaLabel(n)" :aria-pressed="state(n) === 'S'"
                        :disabled="!['D', 'S'].includes(state(n))"
                        @click="pick(n, $el)">
                        <span x-text="label(n)"></span>
                        <i class="bi bi-check-lg rf-num__icon" x-show="state(n) === 'S'" aria-hidden="true"></i>
                    </button>
                </template>
            </div>
            <p class="rf-panel__hint" x-show="onlyFree && freeNumbers.length === 0" x-cloak><strong>{{t:raffle_js.random_none}}</strong></p>
            <button type="button" class="rf-btn rf-btn--ghost rf-btn--block rf-more" x-show="onlyFree && freeRemaining > 0" x-cloak @click="showMoreFree()">
                {{t:raffle.show_more}} <span x-text="'(' + freeRemaining + ')'"></span>
            </button>
        </div>
    </section>

    <!-- Descrição e regulamento (fim da página) -->
    <section class="rf-rules {{rulesHidden}}" x-data="{ open: false }" aria-labelledby="rf-rules-title">
        <h2 id="rf-rules-title" class="rf-rules__title"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{t:raffle.rules_title}}</h2>
        <p class="rf-rules__summary">{{rulesSummary}}</p>
        <button type="button" class="rf-link" @click="open = !open" :aria-expanded="open" aria-controls="rf-rules-full">
            <span x-text="open ? $t('rules_hide') : $t('rules_show')">{{t:raffle_js.rules_show}}</span>
            <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i>
        </button>
        <div id="rf-rules-full" class="rf-rules__full" x-show="open" x-collapse x-cloak>
            <div>{{description}}</div>
            <div>{{draw_rules}}</div>
        </div>
    </section>

</div>

<!-- Barra fixa + drawer de checkout -->
<div x-data="raffleCheckout" @rf-checkout.window="start($event.detail)" @keydown.escape.window="close()">
    <div class="rf-bar" x-show="visible" x-transition:enter="rf-bar--anim" x-transition:enter-start="rf-bar--from" x-transition:leave="rf-bar--anim" x-transition:leave-end="rf-bar--from" x-cloak role="region" aria-label="{{t:raffle.cart_aria}}">
        <div class="rf-bar__inner">
            <button type="button" class="rf-bar__summary" @click="expanded = !expanded" :aria-expanded="expanded" aria-controls="rf-bar-chips">
                <template x-if="cart.count > 0">
                    <span><strong class="rf-bump-target" x-ref="count" x-text="countLabel"></strong> <span x-text="$tc('numbers_word', cart.count)"></span></span>
                </template>
                <template x-if="cart.count === 0">
                    <span x-text="$tc('reserved_count', cart.myReserved.length)"></span>
                </template>
                <i class="bi" :class="expanded ? 'bi-chevron-down' : 'bi-chevron-up'" aria-hidden="true" x-show="cart.count > 0"></i>
            </button>
            <button type="button" class="rf-bar__clear" x-show="cart.count > 0" @click="cart.clear()" aria-label="{{t:raffle.clear_aria}}">{{t:raffle.clear}}</button>
            <div class="rf-bar__total">
                <small>{{t:raffle.total}}</small>
                <strong class="rf-bump-target" x-ref="total" x-text="shownTotalLabel" aria-hidden="true"></strong>
                <span class="rf-sr-only" aria-live="polite" aria-atomic="true" x-text="liveSummary"></span>
            </div>
            <button type="button" class="rf-btn rf-btn--primary rf-bar__pay" :class="{ 'is-ready': canPay }" :disabled="!canPay" @click="start('pay')">
                <i class="bi bi-bag-check" aria-hidden="true" x-show="canPay"></i> <span x-text="payLabel">{{t:raffle_js.pick_at_least_one}}</span>
            </button>
        </div>
        <div id="rf-bar-chips" class="rf-bar__chips" x-show="expanded && cart.count > 0" x-collapse>
            <template x-for="n in sorted" :key="n">
                <button type="button" class="rf-chip rf-chip--selected" @click="cart.remove(n)" :aria-label="$t('remove_number', { number: label(n) })">
                    <span x-text="label(n)"></span> <i class="bi bi-x" aria-hidden="true"></i>
                </button>
            </template>
        </div>
    </div>

    <div class="rf-overlay" x-show="open" x-transition.opacity x-cloak @click="close()"></div>
    <div class="rf-drawer" x-show="open" x-cloak x-transition:enter="rf-drawer--enter" x-transition:enter-start="rf-drawer--from" x-transition:leave="rf-drawer--enter" x-transition:leave-end="rf-drawer--from"
        role="dialog" aria-modal="true" aria-labelledby="rf-drawer-title">
        <div class="rf-drawer__head">
            <h2 id="rf-drawer-title" x-text="mode === 'lookup' ? $t('drawer_lookup') : $t('drawer_pay')">{{t:raffle_js.drawer_pay}}</h2>
            <button type="button" class="rf-icon-btn" @click="close()" aria-label="{{t:raffle.close}}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>

        <div class="rf-drawer__body">
            <p class="rf-alert rf-alert--warning" x-ref="notice" x-show="notice" x-text="notice" role="alert"></p>

            <!-- Identificação -->
            <form x-show="step === 'phone' || step === 'name'" @submit.prevent="identify()" novalidate>
                <p class="rf-drawer__lead" x-show="mode !== 'lookup'">{{t:raffle.checkout_lead}}</p>
                <p class="rf-muted" x-show="mode !== 'lookup'">
                    <strong x-text="cart.count"></strong> <span x-text="$tc('numbers_word', cart.count)"></span> · <strong x-text="totalLabel"></strong>
                </p>
                <label for="rf-phone" class="rf-label">{{t:raffle.phone_label}}</label>
                <input id="rf-phone" type="tel" inputmode="tel" autocomplete="tel" class="rf-input" x-model="phone" x-mask="{{t:raffle.phone_mask}}" placeholder="{{t:raffle.phone_placeholder}}" :readonly="step === 'name'" aria-describedby="rf-phone-help" :aria-invalid="error ? 'true' : 'false'" required>
                <p id="rf-phone-help" class="rf-muted rf-small">{{t:raffle.phone_help}}</p>
                <template x-if="step === 'name'">
                    <div>
                        <label for="rf-name" class="rf-label">{{t:raffle.name_label}}</label>
                        <input id="rf-name" type="text" autocomplete="name" class="rf-input" x-model="name" placeholder="{{t:raffle.name_placeholder}}" required>
                    </div>
                </template>
                <p class="rf-error" x-show="error" x-text="error" role="alert"></p>
                <button type="submit" class="rf-btn rf-btn--primary rf-btn--block" :disabled="loading">
                    <span x-show="!loading" x-text="mode === 'lookup' ? $t('btn_lookup') : $t('btn_continue')">{{t:raffle_js.btn_continue}}</span>
                    <span x-show="loading">{{t:raffle.wait}}</span>
                </button>
            </form>

            <!-- Reservando -->
            <div x-show="step === 'reserving'" class="rf-center" role="status">
                <div class="rf-spinner" aria-hidden="true"></div>
                <p>{{t:raffle.reserving}}</p>
            </div>

            <!-- Erro -->
            <div x-show="step === 'error'">
                <p class="rf-error" x-text="error" role="alert"></p>
                <button type="button" class="rf-btn rf-btn--outline rf-btn--block" @click="close()">{{t:raffle.back_to_pick}}</button>
            </div>

            <!-- Pagamento -->
            <div x-show="step === 'pay'">
                <div class="rf-reserved">
                    <p><i class="bi bi-check-circle-fill" aria-hidden="true"></i> {{t:raffle.reserved_numbers}} <strong x-text="payNumbers"></strong></p>
                    <p class="rf-reserved__total">{{t:raffle.total_colon}} <strong x-text="payTotalLabel"></strong></p>
                    <p class="rf-timer" :class="{ 'is-urgent': urgent }" x-show="remaining">{{t!:raffle.timer}}</p>
                </div>

                <template x-if="mercadoPago">
                    <a :href="mercadoPago" class="rf-btn rf-btn--primary rf-btn--block"><i class="bi bi-credit-card" aria-hidden="true"></i> {{t:raffle.pay_mercado_pago}}</a>
                </template>
                <template x-if="!mercadoPago">
                    <div class="rf-pix" role="group" aria-labelledby="rf-pix-title">
                        <p class="rf-label" id="rf-pix-title">{{t:raffle.pay_pix}}</p>
                        <div x-html="pixHtml"></div>
                        <a :href="finishUrl" class="rf-btn rf-btn--outline rf-btn--block">{{t:raffle.already_paid}}</a>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <template x-ref="initialPix">{{pagePaymentPix}}</template>
</div>

<!-- Toast -->
<div x-data="raffleToast" @rf-toast.document="show($event.detail)" class="rf-toast" x-show="message" x-transition.opacity x-cloak role="status" aria-live="polite">
    <span x-text="message"></span>
</div>

<a class="rf-whats" :class="{ 'rf-whats--raised': $store.cart.count > 0 || $store.cart.myReserved.length > 0 }" x-data
    href="https://wa.me/{{phone_whatsapp}}?text={{t:raffle.whatsapp_float_message}}" target="_blank" rel="noopener" aria-label="{{t:raffle.whatsapp_float_aria}}">
    <i class="bi bi-whatsapp" aria-hidden="true"></i>
</a>

<script>window.__RAFFLE__ = {{raffleData}};</script>
