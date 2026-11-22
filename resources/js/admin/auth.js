/**
 * Login / cadastro do painel (/admin/login): abas acessíveis (setas trocam), mostrar senha e conferência da confirmação.
 * Raiz: <div x-data="authForm" data-tab="login|register">.
 */
export default function authForm() {
    return () => ({
        tab: 'login',
        show: false,
        password: '',
        confirm: '',

        init() {
            this.tab = this.$root.dataset.tab === 'register' ? 'register' : 'login';
        },

        get mismatch() {
            return this.confirm !== '' && this.confirm !== this.password;
        },

        select(tab, focus = false) {
            this.tab = tab;
            this.show = false;
            if (focus) {
                this.$nextTick(() => document.getElementById(`adm-tab-${tab}`)?.focus());
            }
        },

        checkRegister(event) {
            if (this.password !== this.confirm) {
                event.preventDefault();
                document.getElementById('register-confirm')?.focus();
            }
        },
    });
}
