/**
 * Formulário de criação/edição de rifa (/admin/sortition/create | /{id}/edit).
 *
 * Dados: <script type="application/json" id="adm-raffle-form-data">
 *   { editing, locked, showFee, fee, currency, decimal, thousands, maxKb, hasErrors, photos: [{ key, url }], deleteUrl }
 * - Resumo ao vivo: quantidade × preço = potencial; custo de liberação = quantidade × tarifa.
 * - Preço com $money (@alpinejs/mask) nos separadores do idioma; o servidor aceita os dois formatos (Money::parse).
 * - Fotos: arrastar/soltar ou escolher; prévia local; arquivos acima do limite são recusados antes do envio.
 *   Fotos já salvas são removidas com confirmação (fetch → deleteImageSortition, JSON).
 */

const PRESETS = [100, 500, 1000, 10000];
const DAY = 86_400_000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/** Mesmas regras do Money::parse no servidor. */
export function parseMoney(value) {
    let clean = String(value ?? '').replace(/[^\d,.-]/g, '');
    if (!/\d/.test(clean)) {
        return 0;
    }
    clean = clean.replace(/-/g, '');
    const comma = clean.lastIndexOf(',');
    const dot = clean.lastIndexOf('.');
    let decimal = null;
    if (comma >= 0 && dot >= 0) {
        decimal = comma > dot ? ',' : '.';
    } else if (comma >= 0 || dot >= 0) {
        const separator = comma >= 0 ? ',' : '.';
        const parts = clean.split(separator);
        decimal = parts.length === 2 && parts[1].length !== 3 ? separator : null;
    }
    if (decimal === null) {
        return Number(clean.replace(/\D/g, ''));
    }
    const position = clean.lastIndexOf(decimal);
    const integer = clean.slice(0, position).replace(/\D/g, '') || '0';
    const fraction = clean.slice(position + 1).replace(/\D/g, '') || '0';
    return Math.round(Number(`${integer}.${fraction}`) * 100) / 100;
}

export default function raffleForm({ t, locale }) {
    const integer = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 });
    const relative = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    const dateFormat = new Intl.DateTimeFormat(locale, { dateStyle: 'full', timeStyle: 'short' });

    return () => {
        const data = (() => {
            try {
                return JSON.parse(document.getElementById('adm-raffle-form-data')?.textContent || '{}');
            } catch {
                return {};
            }
        })();
        const moneyFormat = new Intl.NumberFormat(locale, { style: 'currency', currency: data.currency || 'BRL' });
        let seq = 0;

        return {
            presets: PRESETS,
            locked: Boolean(data.locked),
            showFee: Boolean(data.showFee),
            fee: Number(data.fee) || 0,
            decimal: data.decimal || ',',
            thousands: data.thousands || '.',
            maxKb: Number(data.maxKb) || 5120,
            photos: Array.isArray(data.photos) ? data.photos : [],
            files: [],
            title: '',
            total: '',
            price: '',
            drawDate: '',
            dragging: false,
            busy: false,
            submitting: false,
            summaryOpen: false,

            init() {
                // Valores iniciais vêm do HTML (old() do servidor ou dados da rifa).
                this.title = this.$el.querySelector('#title')?.value ?? '';
                this.total = Number(this.$el.querySelector('#generated_numbers')?.value) || '';
                this.price = this.$el.querySelector('#price')?.value ?? '';
                this.drawDate = this.$el.querySelector('#date_sortition')?.value ?? '';
                if (data.hasErrors) {
                    requestAnimationFrame(() => this.$el.querySelector('[aria-invalid="true"]')?.focus());
                }
                window.addEventListener('pageshow', () => {
                    this.submitting = false;
                });
            },

            int(value) {
                return integer.format(Number(value) || 0);
            },
            money(value) {
                return moneyFormat.format(Number(value) || 0);
            },

            get totalInt() {
                return Math.max(0, Math.floor(Number(this.total) || 0));
            },
            get priceValue() {
                return parseMoney(this.price);
            },
            get potential() {
                return Math.round(this.totalInt * this.priceValue * 100) / 100;
            },
            get releaseCost() {
                return Math.round(this.totalInt * this.fee * 100) / 100;
            },
            get drawLabel() {
                if (!this.drawDate) {
                    return '';
                }
                const date = new Date(this.drawDate);
                if (Number.isNaN(date.getTime())) {
                    return '';
                }
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const day = new Date(date);
                day.setHours(0, 0, 0, 0);
                const days = Math.round((day - today) / DAY);
                const when = days < 0 ? t('form_date_past') : relative.format(days, 'day');
                return `${dateFormat.format(date)} · ${when}`;
            },

            /* ---------- Fotos ---------- */
            onPick(event) {
                this.addFiles(event.target.files);
            },
            onDrop(event) {
                this.dragging = false;
                this.addFiles(event.dataTransfer?.files);
            },
            addFiles(list) {
                const rejected = [];
                for (const file of [...(list ?? [])]) {
                    if (!file.type.startsWith('image/')) {
                        rejected.push(t('form_photo_not_image', { name: file.name }));
                        continue;
                    }
                    if (file.size / 1024 > this.maxKb) {
                        rejected.push(t('form_photo_too_big', { name: file.name, size: Math.round(this.maxKb / 1024) }));
                        continue;
                    }
                    const duplicate = this.files.some((item) => item.name === file.name && item.file.size === file.size);
                    if (!duplicate) {
                        this.files.push({ id: ++seq, file, name: file.name, url: URL.createObjectURL(file) });
                    }
                }
                rejected.forEach((message) => this.$store.toast.error(message));
                this.syncInput();
            },
            removeFile(id) {
                const item = this.files.find((entry) => entry.id === id);
                if (item) {
                    URL.revokeObjectURL(item.url);
                }
                this.files = this.files.filter((entry) => entry.id !== id);
                this.syncInput();
            },
            /** O <input type=file> passa a conter exatamente os arquivos da prévia. */
            syncInput() {
                const input = this.$refs.files;
                if (!input) {
                    return;
                }
                try {
                    const transfer = new DataTransfer();
                    this.files.forEach((item) => transfer.items.add(item.file));
                    input.files = transfer.files;
                } catch {
                    /* navegador sem DataTransfer: fica o que o usuário escolheu no input */
                }
            },
            async removePhoto(photo) {
                const ok = await this.$store.confirm.ask({
                    title: t('form_photo_remove_title'),
                    message: t('form_photo_remove_text'),
                    confirmLabel: t('form_photo_remove'),
                    danger: true,
                });
                if (!ok || !data.deleteUrl) {
                    return;
                }
                this.busy = true;
                try {
                    const body = new FormData();
                    body.append('keyPhoto', String(photo.key));
                    const response = await fetch(data.deleteUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body,
                    });
                    const json = await response.json().catch(() => ({}));
                    if (response.ok && json.status === 'success') {
                        this.photos = Array.isArray(json.photos) ? json.photos : this.photos.filter((item) => item.key !== photo.key);
                        this.$store.toast.success(json.message || t('form_photo_removed'));
                    } else {
                        this.$store.toast.error(json.message || t('error_generic'));
                    }
                } catch {
                    this.$store.toast.error(t('error_generic'));
                } finally {
                    this.busy = false;
                }
            },

            onSubmit(event) {
                if (this.submitting) {
                    event.preventDefault();
                    return;
                }
                window.tinymce?.triggerSave?.();
                this.submitting = true;
            },
        };
    };
}
