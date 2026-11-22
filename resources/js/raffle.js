/**
 * Página da rifa (/share/{codigo}) — Alpine.
 *
 * Contrato com o servidor: window.__RAFFLE__ (SharedController::raffleData)
 *   { shareCode, price, currency, locale, i18n, total, status: "DRU...", mine: { reserved: [], paid: [] },
 *     customer: { id, name } | null, reservationMinutes,
 *     endpoints: { reserve, register, mercadoPago, finish, myNumbers } }
 *
 * Textos: i18n vem de lang/{locale}/raffle_js.php. t('chave', {param}) e tc('chave', n, {param});
 * nos templates Alpine: $t(...) e $tc(...).
 *
 * Store "cart": seleção só no cliente (sessionStorage); nada é reservado ao clicar.
 * A reserva acontece em lote no checkout: Alpine.store('cart').reserve().
 * Componentes: raffleGrid (seletor de números) e raffleCheckout (barra fixa + drawer).
 */
import Alpine from 'alpinejs';
import mask from '@alpinejs/mask';
import collapse from '@alpinejs/collapse';

const raffle = window.__RAFFLE__ ?? null;
const messages = raffle?.i18n ?? {};
const locale = raffle?.locale ?? document.documentElement.lang ?? 'pt-BR';
const currency = raffle?.currency ?? 'BRL';

/** Substitui :param (mais longos primeiro: ":numbers" antes de ":number"). */
function fill(text, params) {
    return Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .reduce((result, name) => result.split(`:${name}`).join(String(params[name])), text);
}

/** Tradução com placeholders no estilo Laravel (:param). Chave ausente = a própria chave. */
function t(key, params = {}) {
    return fill(messages[key] ?? key, params);
}

/** Plural "singular|plural" (1 = singular; 0 e demais = plural). :count é preenchido automaticamente. */
function tc(key, count, params = {}) {
    const forms = (messages[key] ?? key).split('|');
    return fill(count === 1 || forms.length === 1 ? forms[0] : forms[1], { count, ...params });
}

const storageKey = raffle ? `raffle-cart:${raffle.shareCode}` : null;

const storage = {
    read() {
        try {
            const value = JSON.parse(sessionStorage.getItem(storageKey) ?? '[]');
            return Array.isArray(value) ? value.filter(Number.isInteger) : [];
        } catch {
            return [];
        }
    },
    write(numbers) {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(numbers));
        } catch {
            // sessionStorage indisponível (aba privada etc.): o carrinho segue só em memória
        }
    },
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));

    return { ok: response.ok, status: response.status, data };
}

/** Rótulo padronizado igual a Raffle::label(): zeros à esquerda com o nº de dígitos do total. */
const digits = String(raffle?.total ?? 0).length;
const label = (number) => String(number).padStart(digits, '0');
/** "01", "01 e 02", "01, 02 e 03" (conjunção traduzida). */
const listLabel = (numbers) => {
    const labels = [...numbers].sort((a, b) => a - b).map(label);
    return labels.length > 1 ? `${labels.slice(0, -1).join(', ')} ${t('and')} ${labels.at(-1)}` : (labels[0] ?? '');
};
const moneyFormat = (() => {
    try {
        return new Intl.NumberFormat(locale, { style: 'currency', currency });
    } catch {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    }
})();
const money = (value) => moneyFormat.format(Number(value) || 0);

function toast(message) {
    document.dispatchEvent(new CustomEvent('rf-toast', { detail: message }));
}

/* ---------- Microinterações (só transform/opacity; desligadas em prefers-reduced-motion) ---------- */
const motionQuery = window.matchMedia?.('(prefers-reduced-motion: reduce)');
const reducedMotion = () => Boolean(motionQuery?.matches);

/** Reinicia uma animação CSS por classe; não bloqueia cliques (nada de pointer-events/await). */
function replay(element, className, delay = 0) {
    if (!element || reducedMotion()) {
        return;
    }
    element.classList.remove(className);
    element.style.animationDelay = delay ? `${delay}ms` : '';
    void element.offsetWidth; // força reflow para a animação recomeçar
    element.classList.add(className);
    element.addEventListener(
        'animationend',
        () => {
            element.classList.remove(className);
            element.style.animationDelay = '';
        },
        { once: true },
    );
}

function haptic() {
    try {
        navigator.vibrate?.(10);
    } catch {
        // sem suporte
    }
}

function isOnScreen(element) {
    const rect = element.getBoundingClientRect();
    return rect.bottom > 0 && rect.top < window.innerHeight;
}

/** Feedback "✓ copiado" no próprio botão por 2 s. */
function flashCopied(button, text) {
    if (!button) {
        toast(text);
        return;
    }
    button.dataset.rfOriginal ??= button.innerHTML;
    clearTimeout(Number(button.dataset.rfTimer));
    button.innerHTML = `<i class="bi bi-check-lg" aria-hidden="true"></i> ${text}`;
    button.classList.add('is-copied');
    button.dataset.rfTimer = String(
        setTimeout(() => {
            button.innerHTML = button.dataset.rfOriginal;
            button.classList.remove('is-copied');
            delete button.dataset.rfOriginal;
            delete button.dataset.rfTimer;
        }, 2000),
    );
}

async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        return false;
    }
}

/**
 * Tween numérico do motion (animateValue = núcleo do `animate`; o `animate` completo estoura o orçamento de 35 kB).
 * Carregado em segundo plano quando o navegador fica ocioso; até lá os valores mudam sem tween.
 */
let motionLib = null;
const loadMotion = () =>
    import('./raffle-tween.js')
        .then((module) => (motionLib = module))
        .catch(() => {});
if (!reducedMotion()) {
    (window.requestIdleCallback ?? ((callback) => setTimeout(callback, 1500)))(loadMotion);
}

/** Confetti só em pagamento confirmado; a biblioteca é carregada sob demanda. */
async function celebrate() {
    if (reducedMotion()) {
        return;
    }
    try {
        const { default: confetti } = await import('canvas-confetti');
        confetti({ particleCount: 120, spread: 75, origin: { y: 0.7 }, zIndex: 1070, disableForReducedMotion: true });
    } catch {
        // falha ao carregar o chunk: sem confetti, sem problema
    }
}

/**
 * Pagamento confirmado = o servidor passou a listar mais números pagos deste cliente do que na última visita
 * (o retorno do MP e a baixa manual do Pix acontecem fora desta página).
 */
const paidKey = (customer) => `raffle-paid:${raffle.shareCode}:${customer.id}`;

function rememberPaid(customer) {
    if (!raffle || !customer) {
        return null;
    }
    try {
        const previous = localStorage.getItem(paidKey(customer));
        localStorage.setItem(paidKey(customer), String(raffle.mine.paid.length));
        return previous;
    } catch {
        return null;
    }
}

function checkPaymentConfirmed() {
    const previous = rememberPaid(raffle?.customer);
    if (previous !== null && raffle.mine.paid.length > Number(previous)) {
        toast(t('payment_confirmed', { numbers: listLabel(raffle.mine.paid), date: raffle.drawDate }));
        celebrate();
    }
}

const cart = {
    numbers: [],
    /** Um caractere por número (1-based): 'D' | 'R' | 'U'. Reativo: a grade se atualiza após reservas/conflitos. */
    status: raffle?.status ?? '',
    customer: raffle?.customer ?? null,
    myReserved: raffle?.mine.reserved ?? [],
    busy: false,
    lastResult: null,

    init() {
        // Descarta da sessão números que deixaram de estar disponíveis desde a última visita
        this.numbers = storage.read().filter((number) => this.statusOf(number) === 'D');
        storage.write(this.numbers);
    },

    get count() {
        return this.numbers.length;
    },

    /** Estimativa local; o valor oficial vem do servidor na reserva. */
    get total() {
        return Math.round(this.count * (raffle?.price ?? 0) * 100) / 100;
    },

    statusOf(number) {
        return this.status.charAt(number - 1) || 'U';
    },

    /** Marca números com um status (ex.: conflitos viram 'R'). */
    markStatus(numbers, code) {
        if (!numbers.length) {
            return;
        }
        const chars = this.status.split('');
        numbers.forEach((number) => {
            if (number >= 1 && number <= chars.length) {
                chars[number - 1] = code;
            }
        });
        this.status = chars.join('');
    },

    has(number) {
        return this.numbers.includes(number);
    },

    isAvailable(number) {
        return this.statusOf(number) === 'D';
    },

    isMine(number) {
        return this.myReserved.includes(number);
    },

    toggle(number) {
        if (this.has(number)) {
            this.remove(number);
        } else if (this.isAvailable(number)) {
            this.numbers.push(number);
            storage.write(this.numbers);
        }
    },

    /** Adiciona vários números (ignora indisponíveis e repetidos). */
    addMany(numbers) {
        const set = new Set(this.numbers);
        numbers.forEach((number) => {
            if (this.isAvailable(number)) {
                set.add(number);
            }
        });
        this.numbers = [...set];
        storage.write(this.numbers);
    },

    remove(number) {
        this.numbers = this.numbers.filter((item) => item !== number);
        storage.write(this.numbers);
    },

    clear() {
        this.numbers = [];
        storage.write(this.numbers);
    },

    /** Identificação no checkout: telefone -> (se novo) nome. Retorna { ok, needsName, data }. */
    async register(phone, name = '') {
        const { ok, data } = await postJson(raffle.endpoints.register, {
            phone,
            username: name,
            codeShare: raffle.shareCode,
        });

        if (ok) {
            this.customer = data.customer;
            if (data.csrf_token) {
                document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', data.csrf_token);
            }
        }

        return { ok, needsName: Boolean(data.needs_name), data };
    },

    /** Reserva em lote. Retorna { ok, needsRegister, data: { reserved, conflicts, total_pay, expires_at, pix_html } }. */
    async reserve() {
        if (this.busy || this.count === 0) {
            return { ok: false, needsRegister: false, data: null };
        }

        this.busy = true;
        try {
            const { ok, status, data } = await postJson(raffle.endpoints.reserve, { numbers: this.numbers });

            if (ok) {
                this.myReserved = data.my_reserved ?? this.myReserved;
                this.lastResult = data;
                this.markStatus([...data.reserved, ...data.conflicts], 'R');
                this.numbers = this.numbers.filter((number) => !data.reserved.includes(number) && !data.conflicts.includes(number));
                storage.write(this.numbers);
            }

            return { ok, needsRegister: status === 401, data };
        } finally {
            this.busy = false;
        }
    },
};

const BAND = 100;
const PACKS = [1, 5, 10, 20]; // quantidades sugeridas na Surpresinha (preço proporcional, sem desconto)
const POPULAR_PACK = 5;
const LOW_FREE = 10; // faixa "quase cheia": mostra quantos livres restam
const FREE_PAGE = 100; // filtro "só disponíveis": quantos livres mostrar por vez

/**
 * Contagens por status e livres por faixa numa única varredura, memorizadas pela string de status
 * (só muda após reserva/conflito). Evita varrer 100k números em cada efeito que lê counts/bands.
 */
let scanCache = { status: null, counts: null, bands: null, free: [] };
function scanStatus(status, total) {
    if (scanCache.status === status && scanCache.total === total) {
        return scanCache;
    }
    const counts = { D: 0, R: 0, U: 0, all: total };
    const bands = [];
    const freeList = [];
    for (let start = 1; start <= total; start += BAND) {
        const end = Math.min(start + BAND - 1, total);
        let free = 0;
        for (let n = start; n <= end; n++) {
            const code = status[n - 1];
            if (code in counts) {
                counts[code]++;
            }
            if (code === 'D') {
                free++;
                freeList.push(n);
            }
        }
        bands.push({ index: bands.length, label: `${label(start)}–${label(end)}`, free });
    }
    scanCache = { status, total, counts, bands, free: freeList };
    return scanCache;
}

/** Seletor de números: abas (escolher / surpresinha), faixas de 100 com setas, busca e atalhos. */
function raffleGrid() {
    return {
        mode: 'pick',
        qty: POPULAR_PACK,
        popularPack: POPULAR_PACK,
        band: 0,
        onlyFree: true,
        freeLimit: FREE_PAGE,
        query: '',
        highlight: null,
        highlightTimer: null,
        searchMessage: '',
        suggestion: null,
        ready: false,
        label,

        init() {
            // Skeleton some quando a grade real já está montada; troca de faixa = fade/slide curto
            this.$nextTick(() => (this.ready = true));
            this.$watch('band', () => this.$nextTick(() => replay(this.$refs.grid, 'rf-swap')));
            this.$watch('onlyFree', () => {
                this.freeLimit = FREE_PAGE;
                this.$nextTick(() => replay(this.$refs.grid, 'rf-swap'));
            });
        },

        get total() {
            return raffle?.total ?? 0;
        },

        get counts() {
            return scanStatus(this.$store.cart.status, this.total).counts;
        },

        /** Faixas com quantidade de livres (para o seletor de faixa). */
        get bands() {
            return scanStatus(this.$store.cart.status, this.total).bands;
        },

        /** Livres que ainda podem ser sorteados (fora do carrinho). */
        get available() {
            return Math.max(0, this.counts.D - this.$store.cart.count);
        },

        /** Pacotes da Surpresinha que cabem no que ainda está livre. */
        get packs() {
            return PACKS.filter((size) => size <= this.available);
        },

        price(amount) {
            return money(amount * (raffle?.price ?? 0));
        },

        setQty(value) {
            const amount = parseInt(value, 10) || 1;
            this.qty = Math.min(Math.max(amount, 1), Math.max(this.available, 1));
        },

        /** Todos os livres da rifa (inclui os que já estão no carrinho, para poder desmarcar). */
        get freeNumbers() {
            return scanStatus(this.$store.cart.status, this.total).free;
        },

        /** Livres ainda não exibidos no filtro "só disponíveis". */
        get freeRemaining() {
            return Math.max(0, this.freeNumbers.length - this.freeLimit);
        },

        showMoreFree() {
            this.freeLimit += FREE_PAGE;
        },

        /** Números exibidos: livres (filtro ligado) ou a faixa atual. */
        get visible() {
            if (this.onlyFree) {
                return this.freeNumbers.slice(0, this.freeLimit);
            }
            const start = this.band * BAND + 1;
            const end = Math.min(start + BAND - 1, this.total);
            const list = [];
            for (let n = start; n <= end; n++) {
                list.push(n);
            }
            return list;
        },

        /** Sufixo da opção da faixa: só avisa quando está quase cheia. */
        bandNote(item) {
            if (item.free === 0) {
                return ` · ${t('band_sold_out')}`;
            }
            return item.free <= LOW_FREE ? ` · ${tc('band_free', item.free)}` : '';
        },

        /** Aviso abaixo da navegação quando a faixa atual está quase cheia. */
        get lowNote() {
            const item = this.bands[this.band];
            if (this.onlyFree || this.bands.length < 2 || !item || item.free > LOW_FREE) {
                return '';
            }
            return item.free ? tc('band_low', item.free) : t('band_empty');
        },

        /** Estado visual: S selecionado, M meu reservado, senão D/R/U. */
        state(number) {
            const store = this.$store.cart;
            if (store.has(number)) {
                return 'S';
            }
            if (store.isMine(number)) {
                return 'M';
            }
            return store.statusOf(number);
        },

        ariaLabel(number) {
            return t('number_aria', { number: label(number), state: t(`state_${this.state(number)}`) });
        },

        pick(number, element) {
            const store = this.$store.cart;
            const adding = !store.has(number);
            store.toggle(number);
            if (adding && store.has(number)) {
                haptic();
            }
            replay(element, 'rf-pop');
        },

        /** Stagger de 40 ms nos números escolhidos visíveis; a navegação de faixas pulsa para os que estão fora da tela. */
        animateAdded(numbers) {
            this.$nextTick(() => {
                let outside = false;
                let delay = 0;
                [...numbers]
                    .sort((a, b) => a - b)
                    .forEach((number) => {
                        const element = document.getElementById(`rf-n-${number}`);
                        if (element && isOnScreen(element)) {
                            replay(element, 'rf-pop', delay);
                            delay += 40;
                        } else {
                            outside = true;
                        }
                    });
                if (outside) {
                    replay(this.$refs.nav, 'rf-ping');
                }
            });
        },

        goTo(number) {
            this.mode = 'pick';
            const freeIndex = this.onlyFree ? this.freeNumbers.indexOf(number) : -1;
            if (freeIndex >= 0) {
                this.freeLimit = Math.max(this.freeLimit, Math.ceil((freeIndex + 1) / FREE_PAGE) * FREE_PAGE);
            } else {
                this.onlyFree = false; // número ocupado: mostra na faixa dele
            }
            this.band = Math.floor((number - 1) / BAND);
            this.highlight = number;
            this.$nextTick(() => {
                document.getElementById(`rf-n-${number}`)?.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
            });
            clearTimeout(this.highlightTimer);
            this.highlightTimer = setTimeout(() => (this.highlight = null), 2500);
        },

        nextFree(from) {
            const status = this.$store.cart.status;
            for (let step = 0; step < this.total; step++) {
                const n = ((from - 1 + step) % this.total) + 1;
                if (status[n - 1] === 'D' && !this.$store.cart.has(n)) {
                    return n;
                }
            }
            return null;
        },

        search() {
            const number = parseInt(this.query, 10);
            this.suggestion = null;
            this.searchMessage = '';
            if (!number || number < 1 || number > this.total) {
                this.searchMessage = t('search_range', { from: label(1), to: label(this.total) });
                return;
            }
            this.goTo(number);
            if (!this.$store.cart.isAvailable(number) && !this.$store.cart.has(number)) {
                this.suggestion = this.nextFree(number);
                this.searchMessage = this.suggestion
                    ? t('search_taken_suggest', { number: label(number), suggestion: label(this.suggestion) })
                    : t('search_taken_none', { number: label(number) });
            }
        },

        takeSuggestion() {
            if (this.suggestion) {
                this.$store.cart.toggle(this.suggestion);
                this.goTo(this.suggestion);
                this.suggestion = null;
                this.searchMessage = '';
            }
        },

        /** Sorteia `amount` números livres que ainda não estão no carrinho. */
        randomFree(amount) {
            const status = this.$store.cart.status;
            const inCart = new Set(this.$store.cart.numbers);
            const pool = [];
            for (let n = 1; n <= status.length; n++) {
                if (status[n - 1] === 'D' && !inCart.has(n)) {
                    pool.push(n);
                }
            }
            for (let i = pool.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [pool[i], pool[j]] = [pool[j], pool[i]];
            }
            return pool.slice(0, amount);
        },

        addRandom(amount) {
            const numbers = this.randomFree(amount);
            this.$store.cart.addMany(numbers);
            this.animateAdded(numbers);
            this.setQty(this.qty); // mantém a quantidade dentro do que sobrou livre
            if (numbers.length) {
                haptic();
            }
            toast(numbers.length ? tc('random_done', numbers.length) : t('random_none'));
        },
    };
}

/** Barra fixa + drawer de checkout: telefone -> (se novo) nome -> reserva -> pagamento. */
function raffleCheckout() {
    const tweens = {}; // controles do motion fora do proxy reativo do Alpine

    return {
        open: false,
        expanded: false,
        mode: 'pay',
        step: 'phone',
        phone: '',
        name: '',
        error: '',
        notice: '',
        loading: false,
        result: null,
        expiresAt: null,
        remaining: '',
        urgent: false,
        timer: null,
        pixHtml: '',
        shownCount: 0,
        shownTotal: 0,
        mercadoPago: raffle?.endpoints.mercadoPago ?? null,
        finishUrl: raffle?.endpoints.finish ?? '#',
        label,

        init() {
            this.pixHtml = this.$refs.initialPix?.innerHTML.trim() ?? '';
            this.shownCount = this.cart.count;
            this.shownTotal = this.totalValue;
            this.$watch('cart.count', (value) => this.tween('shownCount', value, this.$refs.count));
            this.$watch('totalValue', (value) => this.tween('shownTotal', value, this.$refs.total));
        },

        /** Tween numérico (motion) + pulse no valor da barra; instantâneo em reduced-motion. */
        tween(key, to, element) {
            tweens[key]?.stop();
            if (reducedMotion() || !this.visible || !motionLib) {
                this[key] = to;
                return;
            }
            tweens[key] = motionLib.animateValue({
                keyframes: [this[key], to],
                duration: 350,
                ease: 'easeOut',
                onUpdate: (value) => (this[key] = value),
            });
            replay(element, 'rf-bump');
        },

        get countLabel() {
            return Math.round(this.shownCount);
        },

        get shownTotalLabel() {
            return money(this.shownTotal);
        },

        get cart() {
            return this.$store.cart;
        },

        get visible() {
            return this.cart.count > 0 || this.cart.myReserved.length > 0;
        },

        get sorted() {
            return [...this.cart.numbers].sort((a, b) => a - b);
        },

        get totalValue() {
            return this.cart.count > 0 ? this.cart.total : this.cart.myReserved.length * (raffle?.price ?? 0);
        },

        get totalLabel() {
            return money(this.totalValue);
        },

        get canPay() {
            return this.cart.count > 0 || this.cart.myReserved.length > 0;
        },

        get payLabel() {
            return this.canPay ? t('pay_label', { total: this.totalLabel }) : t('pick_at_least_one');
        },

        /** Texto final (sem tween) anunciado pelo leitor de tela quando o carrinho muda. */
        get liveSummary() {
            const count = this.cart.count;
            return count > 0 ? tc('live_summary', count, { total: this.totalLabel }) : '';
        },

        get myNumbersUrl() {
            return this.cart.customer ? `${raffle.endpoints.myNumbers}/${this.cart.customer.id}` : null;
        },

        get payTotalLabel() {
            return this.result?.total_pay_label ?? money(this.cart.myReserved.length * (raffle?.price ?? 0));
        },

        get payNumbers() {
            return [...this.cart.myReserved].sort((a, b) => a - b).map(label).join(', ');
        },

        /** Abre o checkout; mode 'lookup' só identifica e leva para "meus números". */
        start(mode = 'pay') {
            this.mode = mode;
            this.error = '';
            this.notice = '';
            if (mode === 'lookup' && this.myNumbersUrl) {
                window.location.href = this.myNumbersUrl;
                return;
            }
            this.open = true;
            if (!this.cart.customer) {
                this.step = 'phone';
                this.focus('rf-phone');
            } else if (this.cart.count > 0) {
                this.reserve();
            } else {
                this.showPayment(null);
            }
        },

        close() {
            this.open = false;
            clearInterval(this.timer);
        },

        focus(id) {
            this.$nextTick(() => setTimeout(() => document.getElementById(id)?.focus(), 50));
        },

        async identify() {
            this.error = '';
            if (this.phone.replace(/\D/g, '').length < 10) {
                this.error = t('invalid_phone');
                return;
            }
            if (this.step === 'name' && this.name.trim().length < 2) {
                this.error = t('ask_name');
                return;
            }
            this.loading = true;
            try {
                const { ok, needsName, data } = await this.cart.register(this.phone, this.step === 'name' ? this.name : '');
                if (ok) {
                    if (this.mode === 'lookup') {
                        window.location.href = this.myNumbersUrl;
                        return;
                    }
                    await (this.cart.count > 0 ? this.reserve() : this.showPayment(null));
                } else if (needsName) {
                    this.step = 'name';
                    this.focus('rf-name');
                } else {
                    this.error = data.message ?? t('generic_error');
                }
            } catch {
                this.error = t('offline');
            } finally {
                this.loading = false;
            }
        },

        async reserve() {
            this.step = 'reserving';
            this.error = '';
            try {
                const { ok, needsRegister, data } = await this.cart.reserve();
                if (needsRegister) {
                    this.cart.customer = null;
                    this.step = 'phone';
                    this.focus('rf-phone');
                    return;
                }
                if (!ok) {
                    this.step = 'error';
                    this.error = data?.message ?? t('reserve_failed');
                    return;
                }
                const conflicts = data.conflicts;
                if (conflicts.length) {
                    this.notice = t('conflicts', { numbers: listLabel(conflicts) });
                    toast(this.notice);
                    this.$nextTick(() => {
                        replay(this.$refs.notice, 'rf-shake');
                        conflicts.forEach((number) => replay(document.getElementById(`rf-n-${number}`), 'rf-shake'));
                    });
                }
                if (!data.reserved.length && !this.cart.myReserved.length) {
                    this.step = 'error';
                    this.error = t('none_reserved');
                    return;
                }
                this.showPayment(data);
            } catch {
                this.step = 'error';
                this.error = t('offline');
            }
        },

        showPayment(data) {
            rememberPaid(this.cart.customer); // base para detectar o pagamento confirmado na próxima visita
            this.result = data;
            if (data?.pix_html) {
                this.pixHtml = data.pix_html;
            }
            this.expiresAt = data?.expires_at ? new Date(data.expires_at) : null;
            this.step = 'pay';
            clearInterval(this.timer);
            this.tick();
            if (this.expiresAt) {
                this.timer = setInterval(() => this.tick(), 1000);
            }
        },

        tick() {
            if (!this.expiresAt) {
                this.remaining = '';
                this.urgent = false;
                return;
            }
            const seconds = Math.max(0, Math.floor((this.expiresAt - Date.now()) / 1000));
            this.remaining = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
            this.urgent = seconds <= 120;
            if (seconds === 0) {
                clearInterval(this.timer);
            }
        },
    };
}

/** Avisos curtos (toast) disparados pelo evento "rf-toast". */
function raffleToast() {
    return {
        message: '',
        timer: null,
        show(message) {
            this.message = message;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => (this.message = ''), 2600);
        },
    };
}

/** Copiar o código Pix (substitui a função inline do template legado, que não roda via x-html). */
window.copyCodeQrCode = async function copyCodeQrCode() {
    const input = document.getElementById('codeQrCode');
    if (!input) {
        return;
    }
    if (!(await copyText(input.value))) {
        input.select();
        document.execCommand('copy');
    }
    haptic();
    flashCopied(document.querySelector('.rf-pix [onclick*="copyCodeQrCode"]'), t('pix_copied'));
};

/** Compartilhar a rifa (Web Share API, senão copia o link com "✓ Link copiado!" no botão). */
window.shareRaffle = async function shareRaffle(button = null) {
    const url = window.location.href.split('?')[0];
    if (navigator.share) {
        try {
            await navigator.share({ title: document.title, text: t('share_text'), url });
            return;
        } catch (error) {
            if (error?.name === 'AbortError') {
                return; // usuário cancelou
            }
        }
    }
    if (await copyText(url)) {
        flashCopied(button, t('link_copied'));
    } else {
        window.prompt(t('copy_link_prompt'), url);
    }
};

/** Galeria do prêmio: Swiper (já carregado pelo layout) com proporção 4:5 + zoom no GLightbox. */
function initGallery() {
    const element = document.querySelector('.rf-hero__swiper');
    if (!element || !window.Swiper || element.swiper) {
        return;
    }
    window.GLightbox?.({ selector: '.rf-hero__swiper .glightbox' });
    new window.Swiper(element, {
        loop: element.querySelectorAll('.swiper-slide').length > 1,
        speed: 400,
        pagination: { el: element.querySelector('.swiper-pagination'), clickable: true },
    });
}

/** Seletor de idioma (<details>): fecha ao clicar fora ou com Esc (devolvendo o foco ao botão). */
document.addEventListener('click', (event) => {
    document.querySelectorAll('.rf-locale[open]').forEach((menu) => {
        if (!menu.contains(event.target)) {
            menu.open = false;
        }
    });
});
document.addEventListener('keydown', (event) => {
    const menu = event.key === 'Escape' ? document.querySelector('.rf-locale[open]') : null;
    if (menu) {
        menu.open = false;
        menu.querySelector('summary')?.focus();
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGallery);
} else {
    initGallery();
}

window.Alpine = Alpine;
Alpine.plugin(mask);
Alpine.plugin(collapse);
Alpine.magic('t', () => t);
Alpine.magic('tc', () => tc);
Alpine.store('cart', cart);
Alpine.data('raffleGrid', raffleGrid);
Alpine.data('raffleCheckout', raffleCheckout);
Alpine.data('raffleToast', raffleToast);
Alpine.start();
checkPaymentConfirmed();
