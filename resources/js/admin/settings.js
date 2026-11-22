/**
 * Configurações da conta (/admin/customer/*).
 *
 *  - x-data="pixKey" data-type="random|email|phone|document"  máscara da chave Pix conforme o tipo
 *  - x-data="passwordForm"                                    medidor de força, mostrar/ocultar e conferência da senha
 *  - <select x-data="bankSelect">                             banco pesquisável (tom-select, import dinâmico)
 */

const digits = (value) => String(value ?? '').replace(/\D/g, '');

export function pixKey({ t }) {
    return () => ({
        type: '',

        init() {
            this.type = this.$el.dataset.type || '';
        },

        mask(input) {
            if (this.type === 'document') {
                return digits(input).length > 11 ? '99.999.999/9999-99' : '999.999.999-999';
            }
            if (this.type === 'phone') {
                return digits(input).length > 10 ? '(99) 99999-9999' : '(99) 9999-99999';
            }
            return 'false';
        },

        onTypeChange() {
            const input = this.$refs.key;
            if (input) {
                input.value = '';
                this.$nextTick(() => input.focus());
            }
        },

        get inputMode() {
            return { document: 'numeric', phone: 'tel', email: 'email' }[this.type] ?? 'text';
        },
        get placeholder() {
            return t(`pix_placeholder_${this.type || 'none'}`);
        },
        get hint() {
            return t(`pix_hint_${this.type || 'none'}`);
        },
    });
}

/** 0–4: tamanho ≥ 8, ≥ 12, maiúsculas+minúsculas, número, símbolo (máx. 4). */
export function passwordScore(password) {
    if (!password) {
        return 0;
    }
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    if (password.length < 6) {
        score = Math.min(score, 1);
    }
    return Math.max(1, Math.min(4, score));
}

export function passwordForm({ t }) {
    return () => ({
        password: '',
        confirmation: '',
        show: false,

        get score() {
            return passwordScore(this.password);
        },
        get strengthLabel() {
            return this.password ? t(`password_strength_${this.score}`) : '';
        },
        get mismatch() {
            return this.confirmation.length > 0 && this.confirmation !== this.password;
        },
        get matches() {
            return this.confirmation.length > 0 && this.confirmation === this.password;
        },

        onSubmit(event) {
            if (this.password !== this.confirmation) {
                event.preventDefault();
                event.stopImmediatePropagation();
                this.$store.toast.error(t('password_mismatch'));
                this.$el.querySelector('#confir_new_pass')?.focus();
            }
        },
    });
}

export function bankSelect({ t }) {
    return () => ({
        async init() {
            const el = this.$el;
            try {
                const [{ default: TomSelect }] = await Promise.all([import('tom-select/base'), import('tom-select/dist/css/tom-select.css')]);
                // eslint-disable-next-line no-new
                new TomSelect(el, {
                    create: false,
                    maxOptions: null,
                    allowEmptyOption: true,
                    placeholder: el.dataset.placeholder || '',
                    searchField: ['text'],
                    render: {
                        no_results: () => `<div class="no-results">${t('bank_no_results')}</div>`,
                    },
                });
            } catch {
                /* sem tom-select: o <select> nativo continua funcionando */
            }
        },
    });
}
