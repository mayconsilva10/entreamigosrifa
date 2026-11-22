/**
 * Gestão da rifa (/admin/sortition/{id}/listSortition): abas Compradores, Bilhetes e Histórico.
 *
 * Dados: <script type="application/json" id="adm-manage-data"> (AdminRaffleManage::payload + config):
 *   { total, price, currency, status: "DRU…" (1 char por número), winners: [n], buyers: [{ id, name, phone, whatsapp, email,
 *     paid: [n], reserved: [n], status: paid|pending|partial, amount, pending_amount, reserved_at, groups: [{ at, status, numbers }] }],
 *     raffleId, title, shareUrl, reservationMinutes, tab, filter, endpoints: { confirm, cancel, assign, lookup } }
 * Ações (fetch + JSON) devolvem { status, message, data } e a tela inteira é atualizada a partir de data.
 * A grade renderiza só a faixa atual (100 números) — rifas de 10.000 números continuam leves.
 */

const PAGE_SIZE = 100;
const CHIPS = 6;
const TABS = ['buyers', 'tickets', 'history'];

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function normalize(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

const digits = (value) => String(value ?? '').replace(/\D/g, '');

export default function raffleManage({ t, tc, locale }) {
    const integer = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 });
    const dateFormat = new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short' });
    const relative = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    return () => {
        // Índices fora do proxy reativo do Alpine (rápidos para 10.000 números).
        let owners = new Map();
        let byId = new Map();
        let moneyFormat = null;

        return {
            total: 0,
            price: 0,
            currency: 'BRL',
            status: '',
            winners: [],
            buyers: [],
            counts: { D: 0, R: 0, U: 0 },
            endpoints: {},
            title: '',
            shareUrl: '',
            reservationMinutes: 30,
            raffleId: '',
            tab: 'buyers',

            // Compradores
            filter: 'all',
            query: '',
            limit: 50,
            shown: [],
            selected: [],
            open: {},
            opened: {},
            updated: [],
            busy: false,

            // Bilhetes
            tFilter: 'all',
            tNumbers: [],
            page: 0,
            picked: [],
            numberQuery: '',
            popover: null,

            // Drawer "Atribuir a comprador"
            drawer: false,
            form: { phone: '', name: '', email: '' },
            lookupState: '',
            formError: '',
            formMessage: '',
            saving: false,
            _lookupSeq: 0,
            _drawerTrigger: null,

            init() {
                const source = document.getElementById('adm-manage-data');
                const data = source ? JSON.parse(source.textContent || '{}') : {};
                this.raffleId = data.raffleId;
                this.title = data.title ?? '';
                this.shareUrl = data.shareUrl ?? '';
                this.endpoints = data.endpoints ?? {};
                this.reservationMinutes = data.reservationMinutes ?? 30;
                this.filter = data.filter ?? 'all';
                this.tab = TABS.includes(data.tab) ? data.tab : 'buyers';
                this.apply(data);

                this.$watch('filter', () => this.refreshBuyers(true));
                this.$watch('query', () => this.refreshBuyers(true));
            },

            /* ---------- Dados ---------- */
            apply(data) {
                this.total = data.total ?? 0;
                this.price = Number(data.price ?? 0);
                if (data.currency && data.currency !== this.currency) {
                    moneyFormat = null;
                }
                this.currency = data.currency ?? 'BRL';
                this.status = data.status ?? '';
                this.winners = data.winners ?? [];

                const counts = { D: 0, R: 0, U: 0 };
                for (let i = 0; i < this.status.length; i++) {
                    counts[this.status[i]] = (counts[this.status[i]] ?? 0) + 1;
                }
                this.counts = counts;

                owners = new Map();
                byId = new Map();
                const buyers = (data.buyers ?? []).map((buyer) => {
                    const all = [...buyer.paid, ...buyer.reserved].sort((a, b) => a - b);
                    const pend = {};
                    buyer.reserved.forEach((n) => {
                        pend[n] = true;
                    });
                    const item = {
                        ...buyer,
                        _chips: all.slice(0, CHIPS),
                        _more: Math.max(0, all.length - CHIPS),
                        _pend: pend,
                        _search: `${normalize(buyer.name)} ${normalize(buyer.email)}`,
                        _phone: digits(buyer.phone),
                    };
                    all.forEach((n) => owners.set(n, buyer.id));
                    byId.set(buyer.id, item);
                    return item;
                });
                this.buyers = buyers;
                this.selected = this.selected.filter((id) => byId.get(id)?.reserved.length > 0);
                this.picked = this.picked.filter((n) => this.statusOf(n) === 'D');
                this.refreshBuyers(false);
                this.refreshTickets();
            },

            async post(url, body) {
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body,
                    });
                    const json = await response.json().catch(() => ({}));
                    if (json.data) {
                        this.apply(json.data);
                    }
                    return { ok: response.ok && json.status === 'success', json };
                } catch {
                    return { ok: false, json: {} };
                }
            },

            /* ---------- Formatação ---------- */
            int(value) {
                return integer.format(value ?? 0);
            },
            money(value) {
                moneyFormat ??= new Intl.NumberFormat(locale, { style: 'currency', currency: this.currency || 'BRL' });
                return moneyFormat.format(value ?? 0);
            },
            label(n) {
                return String(n).padStart(String(this.total).length, '0');
            },
            pct(count) {
                return this.total > 0 ? Math.round((count * 10000) / this.total) / 100 : 0;
            },
            get percent() {
                return this.total > 0 ? Math.floor((this.counts.U * 100) / this.total) : 0;
            },
            dateTime(iso) {
                return iso ? dateFormat.format(new Date(iso)) : '';
            },
            ago(iso) {
                const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
                const units = [
                    ['day', 86400],
                    ['hour', 3600],
                    ['minute', 60],
                ];
                for (const [unit, size] of units) {
                    if (Math.abs(seconds) >= size) {
                        return relative.format(Math.round(seconds / size), unit);
                    }
                }
                return relative.format(0, 'minute');
            },
            isLate(buyer) {
                return Boolean(buyer.reserved_at) && Date.now() - new Date(buyer.reserved_at).getTime() > this.reservationMinutes * 60000;
            },

            /* ---------- Abas ---------- */
            setTab(name) {
                this.tab = name;
                this.popover = null;
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('status');
                    if (name === 'buyers') {
                        url.searchParams.delete('tab');
                    } else {
                        url.searchParams.set('tab', name);
                    }
                    window.history.replaceState(null, '', url);
                } catch {
                    /* URL inválida: ignora */
                }
            },
            moveTab(step) {
                const next = TABS[(TABS.indexOf(this.tab) + step + TABS.length) % TABS.length];
                this.setTab(next);
                this.$nextTick(() => document.getElementById(`adm-tab-${next}`)?.focus());
            },
            onEscape() {
                if (this.drawer) {
                    this.closeDrawer();
                } else if (this.popover) {
                    const n = this.popover.n;
                    this.popover = null;
                    this.$root.querySelector(`.adm-tk[data-number="${n}"]`)?.focus();
                }
            },

            /* ---------- Compradores ---------- */
            get buyerCounts() {
                let pending = 0;
                for (const buyer of this.buyers) {
                    if (buyer.reserved.length > 0) {
                        pending++;
                    }
                }
                return { pending, paid: this.buyers.length - pending, all: this.buyers.length };
            },
            refreshBuyers(resetLimit) {
                const query = this.query.trim();
                const text = normalize(query);
                const number = /^\d+$/.test(query) ? Number(query) : null;
                const phone = digits(query);

                this.shown = this.buyers.filter((buyer) => {
                    if (this.filter === 'pending' && buyer.reserved.length === 0) {
                        return false;
                    }
                    if (this.filter === 'paid' && buyer.reserved.length > 0) {
                        return false;
                    }
                    if (query === '') {
                        return true;
                    }
                    if (buyer._search.includes(text)) {
                        return true;
                    }
                    if (phone.length >= 3 && buyer._phone.includes(phone)) {
                        return true;
                    }
                    return number !== null && owners.get(number) === buyer.id;
                });
                if (resetLimit) {
                    this.limit = 50;
                }
            },
            isSelected(id) {
                return this.selected.includes(id);
            },
            toggleSelect(id) {
                this.selected = this.isSelected(id) ? this.selected.filter((item) => item !== id) : [...this.selected, id];
            },
            get selectableVisible() {
                return this.shown.slice(0, this.limit).filter((buyer) => buyer.reserved.length > 0);
            },
            get allVisibleSelected() {
                const list = this.selectableVisible;
                return list.length > 0 && list.every((buyer) => this.selected.includes(buyer.id));
            },
            get someVisibleSelected() {
                return !this.allVisibleSelected && this.selectableVisible.some((buyer) => this.selected.includes(buyer.id));
            },
            toggleAllVisible(checked) {
                const ids = this.selectableVisible.map((buyer) => buyer.id);
                this.selected = checked ? [...new Set([...this.selected, ...ids])] : this.selected.filter((id) => !ids.includes(id));
            },
            get selectedAmount() {
                return this.selected.reduce((sum, id) => sum + (byId.get(id)?.pending_amount ?? 0), 0);
            },
            isOpen(id) {
                return Boolean(this.open[id]);
            },
            wasOpened(id) {
                return Boolean(this.opened[id]);
            },
            toggleOpen(id, force = null) {
                const value = force ?? !this.open[id];
                if (value) {
                    this.opened[id] = true;
                }
                this.open[id] = value;
            },
            chargeUrl(buyer) {
                const numbers = buyer.reserved.slice(0, 30).map((n) => this.label(n)).join(', ') + (buyer.reserved.length > 30 ? '…' : '');
                const text = t('manage_charge_text', {
                    name: buyer.name.split(' ')[0],
                    title: this.title,
                    numbers,
                    amount: this.money(buyer.pending_amount),
                    url: this.shareUrl,
                });
                return `https://wa.me/${buyer.whatsapp}?text=${encodeURIComponent(text)}`;
            },
            highlight(ids) {
                this.updated = ids;
                setTimeout(() => {
                    if (this.updated === ids) {
                        this.updated = [];
                    }
                }, 1800);
            },

            /** Confirmar pagamento / cancelar reserva de um ou vários compradores (confirm_modal → fetch → toast). */
            async act(kind, ids) {
                const buyers = ids.map((id) => byId.get(id)).filter((buyer) => buyer && buyer.reserved.length > 0);
                if (buyers.length === 0 || this.busy) {
                    return;
                }
                const count = buyers.reduce((sum, buyer) => sum + buyer.reserved.length, 0);
                const amount = this.money(buyers.reduce((sum, buyer) => sum + buyer.pending_amount, 0));
                const who = buyers.length === 1 ? buyers[0].name : tc('manage_buyers_count', buyers.length, { count: buyers.length });
                const confirm = kind === 'confirm';

                const ok = await this.$store.confirm.ask({
                    title: t(confirm ? 'manage_confirm_title' : 'manage_cancel_title'),
                    message: tc(confirm ? 'manage_confirm_text' : 'manage_cancel_text', count, { count: this.int(count), amount, who }),
                    confirmLabel: t(confirm ? 'manage_confirm_ok' : 'manage_cancel_ok'),
                    cancelLabel: t('manage_back'),
                    danger: !confirm,
                });
                if (!ok) {
                    return;
                }

                this.busy = true;
                const body = new FormData();
                buyers.forEach((buyer) => body.append('customers[]', buyer.id));
                const { ok: success, json } = await this.post(this.endpoints[kind], body);
                this.busy = false;

                if (!success) {
                    this.$store.toast.error(json.message || t('error_generic'));
                    return;
                }
                const changed = buyers.map((buyer) => buyer.id);
                this.selected = this.selected.filter((id) => !changed.includes(id));
                this.highlight(changed);
                this.$store.toast.success(json.message);
            },

            /* ---------- Bilhetes ---------- */
            statusOf(n) {
                return this.status[n - 1] ?? 'U';
            },
            isWinner(n) {
                return this.winners.includes(n);
            },
            isPicked(n) {
                return this.picked.includes(n);
            },
            get ticketFilters() {
                const filters = [
                    { key: 'all', label: t('manage_filter_all'), icon: 'bi-grid-3x3-gap', count: this.total },
                    { key: 'D', label: t('manage_filter_free'), icon: 'bi-circle', count: this.counts.D },
                    { key: 'R', label: t('manage_filter_pending'), icon: 'bi-hourglass-split', count: this.counts.R },
                    { key: 'U', label: t('manage_filter_paid'), icon: 'bi-check-circle-fill', count: this.counts.U },
                ];
                if (this.winners.length > 0) {
                    filters.push({ key: 'W', label: t('manage_filter_winners'), icon: 'bi-trophy-fill', count: this.winners.length });
                }
                return filters;
            },
            setTicketFilter(key) {
                this.tFilter = key;
                this.page = 0;
                this.popover = null;
                this.refreshTickets();
            },
            /** Recalcula a lista filtrada da grade (mantém a faixa atual quando ainda existir). */
            refreshTickets() {
                if (this.tFilter === 'all') {
                    this.tNumbers = [];
                } else if (this.tFilter === 'W') {
                    this.tNumbers = [...this.winners].sort((a, b) => a - b);
                } else {
                    const list = [];
                    for (let i = 0; i < this.status.length; i++) {
                        if (this.status[i] === this.tFilter) {
                            list.push(i + 1);
                        }
                    }
                    this.tNumbers = list;
                }
                this.page = Math.min(this.page, Math.max(0, this.pages.length - 1));
            },
            get ticketCount() {
                return this.tFilter === 'all' ? this.total : this.tNumbers.length;
            },
            /** Rótulos das faixas ("0001–0100"). */
            get pages() {
                const labels = [];
                const count = this.ticketCount;
                for (let start = 0; start < count; start += PAGE_SIZE) {
                    const end = Math.min(count, start + PAGE_SIZE) - 1;
                    const first = this.tFilter === 'all' ? start + 1 : this.tNumbers[start];
                    const lastNumber = this.tFilter === 'all' ? end + 1 : this.tNumbers[end];
                    labels.push(`${this.label(first)}–${this.label(lastNumber)}`);
                }
                return labels;
            },
            get pageNumbers() {
                const start = this.page * PAGE_SIZE;
                if (this.tFilter === 'all') {
                    const end = Math.min(this.total, start + PAGE_SIZE);
                    const list = [];
                    for (let n = start + 1; n <= end; n++) {
                        list.push(n);
                    }
                    return list;
                }
                return this.tNumbers.slice(start, start + PAGE_SIZE);
            },
            goPage(index) {
                this.page = Math.max(0, Math.min(index, this.pages.length - 1));
                this.popover = null;
            },
            jumpTo() {
                const n = Number(digits(this.numberQuery));
                if (!n || n > this.total) {
                    this.$store.toast.error(t('manage_number_not_found', { max: this.int(this.total) }));
                    return;
                }
                if (this.tFilter !== 'all' && !this.tNumbers.includes(n)) {
                    this.setTicketFilter('all');
                }
                const index = this.tFilter === 'all' ? n - 1 : this.tNumbers.indexOf(n);
                this.goPage(Math.floor(index / PAGE_SIZE));
                this.$nextTick(() => {
                    const cell = this.$root.querySelector(`.adm-tk[data-number="${n}"]`);
                    if (cell) {
                        cell.focus();
                        cell.classList.remove('is-found');
                        void cell.offsetWidth;
                        cell.classList.add('is-found');
                    }
                });
            },
            cellClass(n) {
                const status = this.statusOf(n);
                return {
                    'is-free': status === 'D' && !this.isPicked(n),
                    'is-picked': status === 'D' && this.isPicked(n),
                    'is-pending': status === 'R',
                    'is-paid': status === 'U',
                    'is-winner': this.isWinner(n),
                    'is-open': this.popover?.n === n,
                };
            },
            cellIcon(n) {
                if (this.isWinner(n)) {
                    return 'bi-trophy-fill';
                }
                const status = this.statusOf(n);
                if (status === 'D') {
                    return this.isPicked(n) ? 'bi-plus-circle-fill' : '';
                }
                return status === 'R' ? 'bi-hourglass-split' : 'bi-check-circle-fill';
            },
            cellLabel(n) {
                const status = this.statusOf(n);
                const name = status === 'D' ? '' : byId.get(owners.get(n))?.name ?? '';
                const key = status === 'D' ? (this.isPicked(n) ? 'manage_cell_picked' : 'manage_cell_free') : status === 'R' ? 'manage_cell_pending' : 'manage_cell_paid';
                return t(key, { number: this.label(n), name }) + (this.isWinner(n) ? ` · ${t('manage_filter_winners')}` : '');
            },
            clickCell(n, event) {
                if (this.statusOf(n) === 'D') {
                    this.popover = null;
                    this.picked = this.isPicked(n) ? this.picked.filter((item) => item !== n) : [...this.picked, n];
                    return;
                }
                if (this.popover?.n === n) {
                    this.popover = null;
                    return;
                }
                const wrap = this.$root.querySelector('.adm-tickets');
                const cell = event.currentTarget.getBoundingClientRect();
                const box = wrap.getBoundingClientRect();
                const width = Math.min(280, box.width);
                const x = Math.max(0, Math.min(cell.left - box.left + cell.width / 2 - width / 2, box.width - width));
                this.popover = { n, buyer: byId.get(owners.get(n)) ?? null, x, y: cell.bottom - box.top + 6 };
                this.$nextTick(() => this.$root.querySelector('[data-popover-focus]')?.focus());
            },
            showBuyer(buyer) {
                this.popover = null;
                this.filter = 'all';
                this.query = buyer.name;
                this.setTab('buyers');
                this.toggleOpen(buyer.id, true);
            },

            /* ---------- Drawer: atribuir números ---------- */
            openDrawer() {
                if (this.picked.length === 0) {
                    return;
                }
                this._drawerTrigger = document.activeElement;
                this.formError = '';
                this.formMessage = '';
                this.drawer = true;
                document.body.style.overflow = 'hidden';
                this.$nextTick(() => document.getElementById('adm-assign-phone')?.focus());
            },
            closeDrawer() {
                if (this.saving) {
                    return;
                }
                this.drawer = false;
                document.body.style.overflow = '';
                this._drawerTrigger?.focus?.();
            },
            async lookup() {
                const phone = digits(this.form.phone);
                if (phone.length < 10) {
                    this.lookupState = '';
                    return;
                }
                const seq = ++this._lookupSeq;
                this.lookupState = 'loading';
                try {
                    const url = new URL(this.endpoints.lookup, window.location.origin);
                    url.searchParams.set('phone', phone);
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    const data = await response.json();
                    if (seq !== this._lookupSeq) {
                        return;
                    }
                    if (data.name) {
                        this.form.name = data.name;
                        this.lookupState = 'found';
                    } else {
                        this.lookupState = 'new';
                    }
                } catch {
                    if (seq === this._lookupSeq) {
                        this.lookupState = '';
                    }
                }
            },
            async saveAssign() {
                const phone = digits(this.form.phone);
                this.formError = phone.length < 10 ? 'phone' : this.form.name.trim() === '' ? 'name' : '';
                if (this.formError) {
                    this.formMessage = t(this.formError === 'phone' ? 'manage_phone_invalid' : 'manage_name_required');
                    document.getElementById(this.formError === 'phone' ? 'adm-assign-phone' : 'adm-assign-name')?.focus();
                    return;
                }

                this.saving = true;
                this.formMessage = '';
                const numbers = [...this.picked];
                const body = new FormData();
                body.append('codeIDSortition', this.raffleId);
                body.append('numbers', numbers.join(','));
                body.append('phone', phone);
                body.append('username', this.form.name.trim());
                body.append('email', this.form.email.trim());
                const { ok, json } = await this.post(this.endpoints.assign, body);
                this.saving = false;

                if (!ok) {
                    this.formMessage = json.message || t('error_generic');
                    return;
                }
                this.picked = [];
                this.form = { phone: '', name: '', email: '' };
                this.lookupState = '';
                this.closeDrawer();
                this.$store.toast.success(json.message);
                this.$nextTick(() => {
                    (json.assigned ?? []).forEach((n) => {
                        const cell = this.$root.querySelector(`.adm-tk[data-number="${n}"]`);
                        cell?.classList.add('is-found');
                    });
                });
            },
        };
    };
}
