/**
 * Ganhadores e encerramento (/admin/sortition/{id}/winnerLottery).
 *
 * Dados: <script id="adm-winners-data">{ previewUrl, total, celebrate, pending }</script>.
 * Fluxo: pick(n) / digitar → check() (GET previewUrl?number=n → prévia) → enviar o formulário confirma o ganhador.
 * Remover ganhador e encerrar a rifa passam por $store.confirm. Confetti (import dinâmico) após registrar um ganhador.
 */

function readData() {
    try {
        return JSON.parse(document.getElementById('adm-winners-data')?.textContent || '{}');
    } catch {
        return {};
    }
}

async function celebrate() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    try {
        const { default: confetti } = await import('canvas-confetti');
        confetti({ particleCount: 140, spread: 80, origin: { y: 0.6 }, zIndex: 1070, disableForReducedMotion: true });
    } catch {
        // sem confetti, sem problema
    }
}

export default function winners({ t, tc }) {
    return () => ({
        data: {},
        number: '',
        preview: null,
        error: '',
        loading: false,
        _checked: null,

        init() {
            this.data = readData();
            if (this.data.celebrate) {
                celebrate();
            }
        },

        pick(value) {
            this.number = value;
            this.check();
            this.$nextTick(() => this.$refs.number?.scrollIntoView({ block: 'center', behavior: 'smooth' }));
        },

        reset() {
            this.preview = null;
            this.error = '';
            this._checked = null;
        },

        clear() {
            this.number = '';
            this.reset();
            this.$refs.number?.focus();
        },

        async check() {
            const value = parseInt(this.number, 10);
            this.reset();
            if (!value || value < 1) {
                this.error = t('winners_invalid');
                this.$refs.number?.focus();
                return false;
            }
            if (this.data.total && value > this.data.total) {
                this.error = t('winners_exceeded', { total: this.data.total });
                return false;
            }

            this.loading = true;
            try {
                const url = `${this.data.previewUrl}?number=${encodeURIComponent(value)}`;
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.error = body.message || t('error_generic');
                    return false;
                }
                this.preview = body;
                this._checked = value;
                return true;
            } catch {
                this.error = t('error_generic');
                return false;
            } finally {
                this.loading = false;
            }
        },

        /** Enter no campo ou "Confirmar": sem prévia do número atual, mostra a prévia antes de gravar. */
        submit(event) {
            if (this._checked !== parseInt(this.number, 10) || !this.preview) {
                event.preventDefault();
                this.check();
            }
        },

        async confirmRemove(form) {
            const ok = await this.$store.confirm.ask({
                title: t('winners_remove_title', { number: form.dataset.label }),
                message: t('winners_remove_text'),
                confirmLabel: t('winners_remove_ok'),
                danger: true,
            });
            if (ok) {
                form.submit();
            }
        },

        async confirmFinish(form) {
            const pending = Number(this.data.pending) || 0;
            const message = [t('winners_finish_text'), pending > 0 ? tc('winners_finish_pending', pending, { count: pending }) : '']
                .filter(Boolean)
                .join(' ');
            const ok = await this.$store.confirm.ask({ title: t('winners_finish_title'), message, confirmLabel: t('winners_finish_ok') });
            if (ok) {
                form.querySelector('button[type=submit]')?.classList.add('is-loading');
                form.submit();
            }
        },
    });
}
