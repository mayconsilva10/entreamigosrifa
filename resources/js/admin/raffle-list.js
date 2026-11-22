/**
 * Lista "Minhas Rifas" (/admin/sortition): diálogos Compartilhar, Personalizar link e Excluir.
 *
 * Raiz: <div x-data="raffleList" data-share-base data-link-endpoint data-link-max>.
 * Cada linha: <tr data-raffle-id data-title data-code data-url>; os itens do menu chamam openDialog('share'|'link'|'delete', $el).
 */

/** Mesmo resultado do Str::slug do servidor para o que dá para digitar: minúsculas, números e hífens. */
export function normalizeCode(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[\s_]+/g, '-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-{2,}/g, '-');
}

const trimHyphens = (value) => value.replace(/^-+|-+$/g, '');

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function raffleList({ t }) {
    return () => ({
        dialog: null,
        raffle: { id: '', title: '', url: '', code: '' },
        code: '',
        error: '',
        saving: false,
        confirmText: '',
        deleting: false,
        canShare: typeof navigator.share === 'function',
        _trigger: null,

        get shareBase() {
            return this.$root.dataset.shareBase ?? '';
        },
        get maxLength() {
            return parseInt(this.$root.dataset.linkMax, 10) || 100;
        },
        get preview() {
            return this.shareBase + (trimHyphens(this.code) || '…');
        },
        get whatsappUrl() {
            return `https://wa.me/?text=${encodeURIComponent(t('share_text', { title: this.raffle.title, url: this.raffle.url }))}`;
        },
        get deleteMatches() {
            const typed = this.confirmText.trim().toLocaleLowerCase();
            return typed !== '' && typed === this.raffle.title.trim().toLocaleLowerCase();
        },

        openDialog(name, el) {
            const row = el.closest('[data-raffle-id]');
            if (!row) {
                return;
            }
            this.raffle = { id: row.dataset.raffleId, title: row.dataset.title, url: row.dataset.url, code: row.dataset.code };
            this.code = this.raffle.code;
            this.error = '';
            this.saving = false;
            this.confirmText = '';
            this.deleting = false;
            this._trigger = row.querySelector('[data-bs-toggle="dropdown"]') ?? el;
            this.dialog = name;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$root.querySelector(`[data-dialog="${name}"] [data-autofocus]`)?.focus());
        },

        closeDialog() {
            if (!this.dialog || this.saving || this.deleting) {
                return;
            }
            this.dialog = null;
            document.body.style.overflow = '';
            this._trigger?.focus?.();
        },

        onCodeInput(event) {
            this.code = normalizeCode(event.target.value);
            event.target.value = this.code;
            this.error = '';
        },

        validate(code) {
            if (code === '') {
                return t('link_empty');
            }
            if (code.length > this.maxLength) {
                return t('link_too_long', { max: this.maxLength });
            }
            return '';
        },

        async saveLink() {
            const code = trimHyphens(normalizeCode(this.code));
            this.error = this.validate(code);
            if (this.error) {
                this.$root.querySelector('#adm-link-code')?.focus();
                return;
            }
            if (code === this.raffle.code) {
                this.closeDialog();
                return;
            }

            this.saving = true;
            try {
                const body = new FormData();
                body.append('idSortitionEditLink', this.raffle.id);
                body.append('newCodeShare', code);
                const response = await fetch(this.$root.dataset.linkEndpoint, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body,
                });
                const data = await response.json();
                if (!response.ok || data.status !== 'success') {
                    this.error = data.message || t('error_generic');
                    return;
                }
                this.updateRow(data.code, data.url);
                this.saving = false;
                this.closeDialog();
                this.$store.toast.success(data.message);
            } catch {
                this.error = t('error_generic');
            } finally {
                this.saving = false;
            }
        },

        /** Atualiza a linha sem recarregar a página (link curto, copiar, visualizar) e destaca a alteração. */
        updateRow(code, url) {
            const row = this.$root.querySelector(`[data-raffle-id="${CSS.escape(String(this.raffle.id))}"]`);
            if (!row) {
                return;
            }
            row.dataset.code = code;
            row.dataset.url = url;
            row.querySelectorAll('[data-link-text]').forEach((el) => {
                el.textContent = url.replace(/^https?:\/\//, '');
            });
            row.querySelectorAll('[data-preview-link]').forEach((link) => {
                link.href = (link.closest('[data-preview-base]')?.dataset.previewBase ?? '') + encodeURIComponent(code);
            });
            row.classList.remove('is-updated');
            void row.offsetWidth; // reinicia a animação
            row.classList.add('is-updated');
        },

        async nativeShare() {
            try {
                await navigator.share({ title: this.raffle.title, text: t('share_text_native', { title: this.raffle.title }), url: this.raffle.url });
            } catch {
                /* cancelado pelo usuário */
            }
        },
    });
}
