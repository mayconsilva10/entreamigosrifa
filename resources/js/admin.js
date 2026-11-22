/**
 * Painel administrativo (/admin) — Alpine.
 *
 * Dados do servidor: window.__ADMIN__ = { locale, i18n } (lang/{locale}/admin_js.php).
 * Textos: t('chave', {param}) / tc('chave', n); nos templates Alpine: $t(...) / $tc(...).
 *
 * Globais:
 *  - $store.toast.push({ type: 'success'|'danger'|'info', message, action?: { label, run }, timeout? })
 *  - await $store.confirm.ask({ title, message, confirmLabel?, cancelLabel?, danger? }) → true/false
 *  - x-copy="'texto'"          copia (Clipboard API) e mostra toast
 *  - x-count-up="1234.5"       anima o número (motion sob demanda; respeita prefers-reduced-motion);
 *                               data-format="money|int|percent" (moeda via data-currency, padrão BRL)
 *  - $store.theme.toggle()     claro/escuro (localStorage em try/catch)
 *  - $store.shell.toggleSidebar()
 *  - .adm-flash[data-flash]    avisos do servidor viram toast
 *  - input[type=file][data-max-size] (KB) bloqueia envio de arquivo grande
 *  - x-data="raffleList"      lista Minhas Rifas (resources/js/admin/raffle-list.js)
 *  - x-data="raffleManage"    gestão de bilhetes e compradores (resources/js/admin/raffle-manage.js)
 *  - x-data="raffleForm"      criação/edição de rifa (resources/js/admin/raffle-form.js)
 *  - x-data="pixKey" | "passwordForm" | "bankSelect"  configurações (resources/js/admin/settings.js)
 *  - x-data="winners"         ganhadores e encerramento (resources/js/admin/winners.js)
 *  - x-data="releaseCheckout" pagamento da liberação: polling do status (resources/js/admin/checkout.js)
 *  - x-data="authForm"        login/cadastro (resources/js/admin/auth.js)
 *  - form[data-adm-submit]     botão de envio com spinner + bloqueio de duplo envio
 */
import Alpine from 'alpinejs';
import mask from '@alpinejs/mask';
import collapse from '@alpinejs/collapse';

const admin = window.__ADMIN__ ?? {};
const messages = admin.i18n ?? {};
const locale = admin.locale ?? document.documentElement.lang ?? 'pt-BR';
const root = document.documentElement;
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

function fill(text, params) {
    return Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .reduce((result, name) => result.split(`:${name}`).join(String(params[name])), text);
}

export function t(key, params = {}) {
    return fill(messages[key] ?? key, params);
}

export function tc(key, count, params = {}) {
    const forms = (messages[key] ?? key).split('|');
    return fill(count === 1 || forms.length === 1 ? forms[0] : forms[1], { count, ...params });
}

const storage = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            /* modo privado / armazenamento bloqueado */
        }
    },
};

/* ---------- Toasts ---------- */
const TOAST_ICONS = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-octagon-fill', info: 'bi-info-circle-fill' };
let toastSeq = 0;

const toast = {
    items: [],
    push({ type = 'info', message = '', action = null, timeout = 5000 } = {}) {
        const id = ++toastSeq;
        this.items.push({ id, type, message, action, icon: TOAST_ICONS[type] ?? TOAST_ICONS.info });
        if (this.items.length > 4) {
            this.items.shift();
        }
        if (timeout > 0) {
            setTimeout(() => this.dismiss(id), action ? Math.max(timeout, 8000) : timeout);
        }
        return id;
    },
    success(message, options = {}) {
        return this.push({ ...options, type: 'success', message });
    },
    error(message, options = {}) {
        return this.push({ ...options, type: 'danger', message });
    },
    dismiss(id) {
        this.items = this.items.filter((item) => item.id !== id);
    },
};

/* ---------- Confirmação assíncrona ---------- */
const confirmStore = {
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    cancelLabel: '',
    danger: false,
    _resolve: null,
    _returnFocus: null,
    ask({ title, message = '', confirmLabel, cancelLabel, danger = false } = {}) {
        if (this._resolve) {
            this._resolve(false);
        }
        this.title = title ?? t('confirm_title');
        this.message = message;
        this.confirmLabel = confirmLabel ?? t('confirm_ok');
        this.cancelLabel = cancelLabel ?? t('confirm_cancel');
        this.danger = danger;
        this._returnFocus = document.activeElement;
        this.open = true;
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => document.querySelector('[data-confirm-ok]')?.focus());

        return new Promise((resolve) => {
            this._resolve = resolve;
        });
    },
    /** Mantém o Tab dentro do diálogo enquanto ele está aberto. */
    trap(event, container) {
        const focusable = [...container.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])')].filter(
            (el) => el.offsetParent !== null,
        );
        if (!focusable.length) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        } else if (!container.contains(document.activeElement)) {
            event.preventDefault();
            first.focus();
        }
    },
    resolve(value) {
        this.open = false;
        document.body.style.overflow = '';
        const resolve = this._resolve;
        this._resolve = null;
        this._returnFocus?.focus?.();
        resolve?.(value);
    },
};

/* ---------- Tema ---------- */
const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');

const theme = {
    value: 'light',
    init() {
        const saved = storage.get('adm-theme');
        this.apply(saved === 'dark' || saved === 'light' ? saved : darkQuery.matches ? 'dark' : 'light');
        darkQuery.addEventListener?.('change', (event) => {
            if (!storage.get('adm-theme')) {
                this.apply(event.matches ? 'dark' : 'light');
            }
        });
    },
    apply(value) {
        this.value = value;
        root.setAttribute('data-theme', value);
    },
    toggle() {
        const next = this.value === 'dark' ? 'light' : 'dark';
        this.apply(next);
        storage.set('adm-theme', next);
        Alpine.store('toast').push({ type: 'info', message: t(next === 'dark' ? 'theme_dark' : 'theme_light'), timeout: 2000 });
    },
};

/* ---------- Shell (sidebar) ---------- */
const shell = {
    collapsed: root.getAttribute('data-sidebar') === 'collapsed',
    toggleSidebar() {
        this.collapsed = !this.collapsed;
        if (this.collapsed) {
            root.setAttribute('data-sidebar', 'collapsed');
        } else {
            root.removeAttribute('data-sidebar');
        }
        storage.set('adm-sidebar', this.collapsed ? 'collapsed' : 'expanded');
    },
};

/* ---------- Telas (carregadas sob demanda) ----------
 * Cada x-data de tela fica num chunk próprio; só os presentes na página são importados
 * antes do Alpine.start(), mantendo o JS inicial dentro do orçamento (decisão 10). */
const SCREENS = {
    raffleList: () => import('./admin/raffle-list.js').then((m) => m.default({ t })),
    raffleManage: () => import('./admin/raffle-manage.js').then((m) => m.default({ t, tc, locale })),
    raffleForm: () => import('./admin/raffle-form.js').then((m) => m.default({ t, locale })),
    pixKey: () => import('./admin/settings.js').then((m) => m.pixKey({ t })),
    passwordForm: () => import('./admin/settings.js').then((m) => m.passwordForm({ t })),
    bankSelect: () => import('./admin/settings.js').then((m) => m.bankSelect({ t })),
    winners: () => import('./admin/winners.js').then((m) => m.default({ t, tc })),
    releaseCheckout: () => import('./admin/checkout.js').then((m) => m.default({ t })),
    authForm: () => import('./admin/auth.js').then((m) => m.default({ t })),
};

function registerScreens() {
    const used = new Set();
    document.querySelectorAll('[x-data]').forEach((el) => {
        const name = el.getAttribute('x-data').trim().match(/^[A-Za-z_$][\w$]*/)?.[0];
        if (name && Object.hasOwn(SCREENS, name)) {
            used.add(name);
        }
    });

    return Promise.all(
        [...used].map((name) =>
            SCREENS[name]()
                .then((factory) => Alpine.data(name, factory))
                .catch((error) => console.error(`[admin] falha ao carregar "${name}"`, error)),
        ),
    );
}

/* ---------- Copiar ---------- */
async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand?.('copy') ?? false;
        area.remove();
        return ok;
    }
}

/* ---------- Count-up ---------- */
function formatNumber(value, format, currency) {
    if (format === 'money') {
        return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(value);
    }
    if (format === 'percent') {
        return new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 0 }).format(value / 100);
    }
    return new Intl.NumberFormat(locale, { maximumFractionDigits: format === 'int' ? 0 : 2 }).format(value);
}

function countUp(el, expression, evaluate) {
    const target = Number(evaluate(expression));
    if (!Number.isFinite(target) || target === 0 || reducedMotion.matches) {
        return;
    }
    const format = el.dataset.format || 'int';
    const currency = el.dataset.currency || 'BRL';
    const finalText = el.textContent;

    import('motion')
        .then(({ animate }) => {
            animate(0, target, {
                duration: 0.8,
                ease: 'easeOut',
                onUpdate: (latest) => {
                    el.textContent = formatNumber(latest, format, currency);
                },
                onComplete: () => {
                    el.textContent = finalText;
                },
            });
        })
        .catch(() => {
            el.textContent = finalText;
        });
}

/* ---------- Avisos do servidor → toast ---------- */
function flashesToToasts() {
    document.querySelectorAll('.adm-flash[data-flash]').forEach((el) => {
        const message = el.querySelector('.adm-flash__message')?.textContent.trim() ?? '';
        if (message) {
            Alpine.store('toast').push({ type: el.dataset.flash === 'success' ? 'success' : 'danger', message, timeout: 6000 });
        }
        el.remove();
    });
}

/* ---------- Seletor de idioma (<details>): fecha ao clicar fora / Esc ---------- */
function closeLocaleMenus(except = null) {
    document.querySelectorAll('details.rf-locale[open]').forEach((menu) => {
        if (menu !== except) {
            menu.removeAttribute('open');
        }
    });
}
document.addEventListener('click', (event) => closeLocaleMenus(event.target.closest?.('details.rf-locale')));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeLocaleMenus();
    }
});

/* ---------- Tamanho máximo de upload ---------- */
document.addEventListener(
    'submit',
    (event) => {
        const inputs = event.target.querySelectorAll?.('input[type=file][data-max-size]') ?? [];
        for (const input of inputs) {
            const maxKb = parseInt(input.dataset.maxSize, 10);
            const tooBig = [...(input.files ?? [])].some((file) => file.size / 1000 > maxKb);
            if (tooBig) {
                event.preventDefault();
                event.stopImmediatePropagation();
                Alpine.store('toast').error(t('file_too_large', { size: maxKb }));
                input.focus();
                return;
            }
        }
    },
    true,
);

/* ---------- Envio de formulário: spinner + sem duplo envio ---------- */
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-adm-submit') || event.defaultPrevented) {
        return;
    }
    if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    form.querySelectorAll('button[type=submit]').forEach((button) => {
        button.classList.add('is-loading');
        button.setAttribute('aria-disabled', 'true');
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-adm-submit][data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('button[type=submit]').forEach((button) => {
            button.classList.remove('is-loading');
            button.removeAttribute('aria-disabled');
        });
    });
});

Alpine.plugin(mask);
Alpine.plugin(collapse);
Alpine.magic('t', () => t);
Alpine.magic('tc', () => tc);
Alpine.store('toast', toast);
Alpine.store('confirm', confirmStore);
Alpine.store('theme', theme);
Alpine.store('shell', shell);

Alpine.directive('copy', (el, { expression }, { evaluate, cleanup }) => {
    const handler = async (event) => {
        event.preventDefault();
        const text = String(evaluate(expression) ?? '');
        if (await copyText(text)) {
            Alpine.store('toast').success(t('copied'), { timeout: 2500 });
        } else {
            Alpine.store('toast').error(t('copy_failed'));
        }
    };
    el.addEventListener('click', handler);
    cleanup(() => el.removeEventListener('click', handler));
});

Alpine.directive('count-up', (el, { expression }, { evaluate }) => countUp(el, expression, evaluate));

window.Alpine = Alpine;
window.adminToast = (type, message, options = {}) => Alpine.store('toast').push({ ...options, type, message });
window.adminConfirm = (options) => Alpine.store('confirm').ask(options);

Alpine.store('theme').init();
registerScreens()
    .catch((error) => console.error(error))
    .finally(() => {
        Alpine.start();
        flashesToToasts();
    });
