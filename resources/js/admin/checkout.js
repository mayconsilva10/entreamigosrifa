/**
 * Pagamento da liberação da rifa (/admin/{id}/checkout).
 *
 * Dados: <script id="adm-checkout-data">{ statusUrl, expiresAt, poll }</script>.
 * Estados: pending → paid (redireciona para Minhas Rifas) | expired (Pix venceu: recarregar gera outro).
 * Polling leve: a cada 10 s por até 15 min, só com a aba visível; o botão "Já paguei, verificar" consulta na hora.
 */

const POLL_MS = 10000;
const POLL_MAX_MS = 15 * 60 * 1000;

function readData() {
    try {
        return JSON.parse(document.getElementById('adm-checkout-data')?.textContent || '{}');
    } catch {
        return {};
    }
}

export default function releaseCheckout({ t }) {
    return () => ({
        data: {},
        state: 'pending',
        checking: false,
        polling: false,
        _timer: null,
        _started: 0,

        init() {
            this.data = readData();
            if (this.isExpired()) {
                this.state = 'expired';
                return;
            }
            if (this.data.poll) {
                this.polling = true;
                this._started = Date.now();
                this.schedule();
            }
        },

        destroy() {
            clearTimeout(this._timer);
        },

        isExpired() {
            const at = this.data.expiresAt ? Date.parse(this.data.expiresAt) : NaN;
            return Number.isFinite(at) && at <= Date.now();
        },

        schedule() {
            clearTimeout(this._timer);
            if (Date.now() - this._started > POLL_MAX_MS) {
                this.polling = false;
                return;
            }
            this._timer = setTimeout(async () => {
                if (document.visibilityState === 'visible') {
                    await this.verify(false);
                }
                if (this.state === 'pending') {
                    this.schedule();
                }
            }, POLL_MS);
        },

        async verify(manual) {
            if (this.checking || this.state === 'paid') {
                return;
            }
            if (this.isExpired()) {
                this.state = 'expired';
                this.polling = false;
                return;
            }
            this.checking = manual;
            try {
                const response = await fetch(this.data.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                if (body.status === 'paid') {
                    this.state = 'paid';
                    this.polling = false;
                    clearTimeout(this._timer);
                    this.$store.toast.success(body.message || t('checkout_paid'));
                    if (body.redirect) {
                        setTimeout(() => window.location.assign(body.redirect), 1200);
                    }
                } else if (manual) {
                    this.$store.toast.push({ type: 'info', message: body.message || t('checkout_pending'), timeout: 5000 });
                }
            } catch {
                if (manual) {
                    this.$store.toast.error(t('error_generic'));
                }
            } finally {
                this.checking = false;
            }
        },
    });
}
