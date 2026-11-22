<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\AdminPaymentHistory;
use App\Support\AdminUi;
use App\Support\LegacyView;
use App\Support\Media;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Configurações da conta do dono da rifa (perfil, senha, pagamentos, redes sociais, notificações, histórico de pagamentos).
 * Navegação lateral (desktop) / lista (mobile), cards por seção, sem jQuery.
 */
class CustomerController extends PanelController
{
    /** Seções (ordem do menu): chave => [rota, ícone]. */
    private const SECTIONS = [
        'profile' => ['/admin/customer/profile', 'bi-person-circle'],
        'password' => ['/admin/customer/getPassword', 'bi-shield-lock'],
        'payment' => ['/admin/customer/payment', 'bi-wallet2'],
        'social' => ['/admin/customer/social', 'bi-share'],
        'notification' => ['/admin/customer/notification', 'bi-bell'],
        'history' => ['/admin/customer/payments-history', 'bi-receipt'],
    ];

    /** Tipos de chave Pix aceitos. */
    public const PIX_TYPES = ['random', 'email', 'phone', 'document'];

    /** Tipos de conta bancária aceitos. */
    public const ACCOUNT_TYPES = ['checkingAccount', 'savingAccount'];

    /** Redes sociais: chave => [ícone, tipo do input]. */
    private const SOCIAL = [
        'whats_group' => ['bi-whatsapp', 'url'],
        'whats_support' => ['bi-headset', 'url'],
        'facebook' => ['bi-facebook', 'url'],
        'instagram' => ['bi-instagram', 'url'],
        'youtube' => ['bi-youtube', 'url'],
    ];

    private const PHOTO_MAX_KB = 5120;

    private function status(Request $request): string
    {
        $types = [
            'updated' => 'success', 'duplicated' => 'danger', 'password_not_updated' => 'danger', 'password_empty' => 'danger',
            'payments_updated' => 'success', 'social_updated' => 'success', 'profile_updated' => 'success',
            'notification_updated' => 'success', 'password_updated' => 'success',
        ];

        $messages = [];
        foreach ($types as $key => $type) {
            $messages[$key] = [$type, e(__('admin.settings.flash.'.$key))];
        }

        return $this->statusMessage($request, $messages);
    }

    /** Layout comum: cabeçalho, navegação das seções e conteúdo da seção atual. */
    private function settingsPage(Request $request, string $active, string $content): string
    {
        $items = '';
        foreach (self::SECTIONS as $key => [$link, $icon]) {
            $current = $key === $active;
            $items .= '<li><a class="adm-settings__link'.($current ? ' is-active' : '').'" href="'.e(url($link)).'"'.($current ? ' aria-current="page"' : '').'>'
                .'<i class="bi '.$icon.'" aria-hidden="true"></i>'
                .'<span class="adm-settings__text"><span class="adm-settings__label">'.e(__('admin.settings.nav.'.$key)).'</span>'
                .'<span class="adm-settings__desc">'.e(__('admin.settings.nav.'.$key.'_desc')).'</span></span>'
                .'<i class="bi bi-chevron-right adm-settings__chev" aria-hidden="true"></i></a></li>';
        }

        $header = LegacyView::render('admin/components/page_header', [
            'breadcrumb' => '',
            'title' => e(__('admin.settings.title')),
            'subtitle' => '<p class="adm-hint">'.e(__('admin.settings.subtitle')).'</p>',
            'actions' => '',
        ]);

        return LegacyView::render('admin/modules/customers/settings/layout', [
            'header' => $header,
            'items' => $items,
            'current_icon' => self::SECTIONS[$active][1],
            'current_label' => e(__('admin.settings.nav.'.$active)),
            'flash' => $this->status($request),
            'content' => $content,
        ]);
    }

    /** Rota antiga /admin/customers: o perfil agora fica em Configurações. */
    public function index(): RedirectResponse
    {
        return redirect('/admin/customer/profile');
    }

    public function profile(Request $request): string
    {
        $customer = $this->customer();

        $content = LegacyView::render('admin/modules/customers/settings/profile', [
            'name' => e($customer->name),
            'email' => e((string) $customer->email),
            'phone' => e((string) $customer->phone),
            'photo_profile' => e($customer->photoUrl()),
            'max_kb' => self::PHOTO_MAX_KB,
            'max_mb' => self::PHOTO_MAX_KB / 1024,
        ]);

        return $this->panel(__('admin.title.profile'), $this->settingsPage($request, 'profile', $content), 'settings');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $request->validate(['photo_profile' => ['nullable', 'image', 'max:'.self::PHOTO_MAX_KB]]);

        $customer = $this->customer();
        $customer->name = $request->input('name') ?: $customer->name;
        $customer->phone = Customer::onlyDigitsPhone($request->input('phone', $customer->phone));

        if ($request->hasFile('photo_profile')) {
            if (! filter_var($customer->photo, FILTER_VALIDATE_URL)) {
                Media::delete($customer->photo);
            }
            $customer->photo = Media::storeImage($request->file('photo_profile'), 'profile');
        }

        $customer->save();

        return redirect('/admin/customer/profile?status=profile_updated');
    }

    public function social(Request $request): string
    {
        $social = $this->customer()->settingsArray()['social'];

        $items = '';
        foreach (self::SOCIAL as $network => [$icon, $type]) {
            $value = $social[$network] ?? '';
            $items .= LegacyView::render('admin/modules/customers/settings/toggle_field', [
                'id' => 'social_'.$network,
                'icon' => $icon,
                'type' => $type,
                'label' => e(__('admin.settings.social.'.$network)),
                'hint' => e(__('admin.settings.social.'.$network.'_hint')),
                'placeholder' => e(__('admin.settings.social.'.$network.'_placeholder')),
                'value' => e($value),
                'on' => $value !== '' ? 'true' : 'false',
            ]);
        }

        $content = LegacyView::render('admin/modules/customers/settings/social', ['items' => $items]);

        return $this->panel(__('admin.title.social'), $this->settingsPage($request, 'social', $content), 'settings');
    }

    public function updateSocial(Request $request): RedirectResponse
    {
        $customer = $this->customer();
        $settings = $customer->settingsArray();

        foreach (array_keys($settings['social']) as $network) {
            $settings['social'][$network] = trim((string) $request->input('social_'.$network, ''));
        }

        $customer->saveSettings($settings);

        return redirect('/admin/customer/social?status=social_updated');
    }

    public function notification(Request $request): string
    {
        $chatId = $this->customer()->settingsArray()['notification']['telegram']['chat_id'];

        $content = LegacyView::render('admin/modules/customers/settings/notification', [
            'telegram' => LegacyView::render('admin/modules/customers/settings/toggle_field', [
                'id' => 'telegram_chat_id',
                'icon' => 'bi-telegram',
                'type' => 'text',
                'label' => e(__('admin.settings.notification.telegram')),
                'hint' => e(__('admin.settings.notification.telegram_hint')),
                'placeholder' => e(__('admin.settings.notification.telegram_placeholder')),
                'value' => e($chatId),
                'on' => $chatId !== '' ? 'true' : 'false',
            ]),
        ]);

        return $this->panel(__('admin.title.notifications'), $this->settingsPage($request, 'notification', $content), 'settings');
    }

    public function updateNotification(Request $request): RedirectResponse
    {
        $customer = $this->customer();
        $settings = $customer->settingsArray();
        $settings['notification']['telegram']['chat_id'] = trim((string) $request->input('telegram_chat_id', ''));
        $customer->saveSettings($settings);

        return redirect('/admin/customer/notification?status=notification_updated');
    }

    public function payment(Request $request): string
    {
        $settings = $this->customer()->settingsArray()['payments'];
        $typeKey = in_array($settings['pix']['type_key'], self::PIX_TYPES, true) ? $settings['pix']['type_key'] : '';
        $bank = $settings['bank'];

        $pixOptions = '';
        foreach (self::PIX_TYPES as $type) {
            $pixOptions .= '<option value="'.$type.'"'.($type === $typeKey ? ' selected' : '').'>'.e(__('admin.settings.pix.types.'.$type)).'</option>';
        }

        $bankOptions = '';
        foreach (config('banks', []) as $code => $name) {
            $value = $code.' - '.$name;
            $bankOptions .= '<option value="'.e($value).'"'.($value === $bank['bank'] ? ' selected' : '').'>'.e($value).'</option>';
        }
        // Banco salvo que não está na lista continua visível
        if ($bank['bank'] !== '' && ! str_contains($bankOptions, 'value="'.e($bank['bank']).'"')) {
            $bankOptions .= '<option value="'.e($bank['bank']).'" selected>'.e($bank['bank']).'</option>';
        }

        $accountOptions = '';
        foreach (self::ACCOUNT_TYPES as $type) {
            $accountOptions .= '<option value="'.$type.'"'.($type === $bank['type_account'] ? ' selected' : '').'>'.e(__('admin.settings.bank.types.'.$type)).'</option>';
        }

        $content = LegacyView::render('admin/modules/customers/settings/payment', [
            'pix' => LegacyView::render('admin/modules/customers/settings/payments/pix', [
                'type_key' => $typeKey,
                'pix_key' => e($settings['pix']['pix_key']),
                'options' => $pixOptions,
            ]),
            // O access token nunca volta para a tela: só um hash para saber se mudou
            'mercado_pago' => LegacyView::render('admin/modules/customers/settings/payments/mercado_pago', [
                'access_token' => hash('sha256', $settings['mercado_pago']['access_token']),
                'public_key' => e($settings['mercado_pago']['public_key']),
                'token_state' => $settings['mercado_pago']['access_token'] !== ''
                    ? '<span class="adm-badge adm-badge--paid"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>'.e(__('admin.settings.mp.token_saved')).'</span>'
                    : '<span class="adm-badge adm-badge--neutral">'.e(__('admin.settings.mp.token_empty')).'</span>',
            ]),
            'bank' => LegacyView::render('admin/modules/customers/settings/payments/bank_account', [
                'banks' => $bankOptions,
                'account_types' => $accountOptions,
                'holder' => e($bank['holder']),
                'document' => e($bank['document']),
                'agency' => e($bank['agency']),
                'account' => e($bank['account']),
            ]),
        ]);

        return $this->panel(__('admin.title.payments'), $this->settingsPage($request, 'payment', $content), 'settings');
    }

    public function updatePayments(Request $request): RedirectResponse
    {
        $customer = $this->customer();
        $settings = $customer->settingsArray();

        $settings['payments']['mercado_pago']['public_key'] = trim((string) $request->input('public_key', ''));

        $postedToken = trim((string) $request->input('access_token', ''));
        if ($postedToken !== hash('sha256', $settings['payments']['mercado_pago']['access_token'])) {
            $settings['payments']['mercado_pago']['access_token'] = $postedToken;
        }

        $type = (string) $request->input('type_key', '');
        $type = in_array($type, self::PIX_TYPES, true) ? $type : '';
        $key = trim((string) $request->input('pix_key', ''));
        // Máscara só existe em CPF/CNPJ e telefone; e-mail e chave aleatória ficam como digitados.
        if (in_array($type, ['document', 'phone'], true)) {
            $key = preg_replace('/\D/', '', $key);
        }
        $settings['payments']['pix']['type_key'] = $type;
        $settings['payments']['pix']['pix_key'] = $key;

        $accountType = (string) $request->input('type_account', '');
        $settings['payments']['bank'] = [
            'bank' => trim((string) $request->input('bank', '')),
            'type_account' => in_array($accountType, self::ACCOUNT_TYPES, true) ? $accountType : '',
            'holder' => trim((string) $request->input('holder', '')),
            'document' => preg_replace('/\D/', '', (string) $request->input('document', '')),
            'agency' => trim((string) $request->input('agency', '')),
            'account' => trim((string) $request->input('account', '')),
        ];

        $customer->saveSettings($settings);

        return redirect('/admin/customer/payment?status=payments_updated');
    }

    public function password(Request $request): string
    {
        $content = LegacyView::render('admin/modules/customers/settings/password');

        return $this->panel(__('admin.title.password'), $this->settingsPage($request, 'password', $content), 'settings');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $new = (string) $request->input('new_pass', '');

        if ($new === '') {
            return redirect('/admin/customer/getPassword?status=password_empty');
        }
        if ($new !== (string) $request->input('confir_new_pass', '')) {
            return redirect('/admin/customer/getPassword?status=password_not_updated');
        }

        $customer = $this->customer();
        $customer->password = Hash::make($new);
        $customer->save();

        return redirect('/admin/customer/getPassword?status=password_updated');
    }

    /** Histórico de pagamentos de liberação das rifas do dono: chips por situação, busca e paginação (?status=&q=&page=). */
    public function paymentsHistory(Request $request, AdminPaymentHistory $history): string
    {
        $result = $history->search($this->customer(), $request->query());
        $base = url('/admin/customer/payments-history');
        $filters = array_filter(['status' => $result['status'], 'q' => $result['q']], fn ($value) => $value !== '');

        $chips = '';
        foreach (['all' => '', ...array_combine(AdminPaymentHistory::STATUSES, AdminPaymentHistory::STATUSES)] as $key => $value) {
            $active = $result['status'] === $value;
            $query = array_filter(array_merge($filters, ['status' => $value]), fn ($v) => $v !== '');
            $chips .= '<a class="adm-chip'.($active ? ' is-active' : '').'" href="'.e($base.($query ? '?'.http_build_query($query) : '')).'"'
                .($active ? ' aria-current="true"' : '').'>'.e(__('admin.history.chips.'.$key))
                .'<span class="adm-chip__count">'.AdminUi::integer($result['counts'][$key]).'</span></a>';
        }

        $rows = '';
        foreach ($result['items'] as $transaction) {
            $rows .= $this->historyRow($transaction);
        }

        $filtered = $filters !== [];
        $body = match (true) {
            $rows !== '' => LegacyView::render('admin/modules/customers/settings/history_table', ['rows' => $rows]),
            default => LegacyView::render('admin/components/empty_state', [
                'icon' => $filtered ? 'bi-search' : 'bi-receipt',
                'title' => e(__($filtered ? 'admin.history.empty.filter_title' : 'admin.history.empty.title')),
                'text' => e(__($filtered ? 'admin.history.empty.filter_text' : 'admin.history.empty.text')),
                'cta' => $filtered
                    ? '<a class="adm-btn adm-btn--secondary" href="'.e($base).'"><i class="bi bi-x-circle" aria-hidden="true"></i>'.e(__('admin.raffles.toolbar.clear')).'</a>'
                    : '',
            ]),
        };

        $content = LegacyView::render('admin/modules/customers/settings/history', [
            'action' => e($base),
            'hidden' => $result['status'] !== '' ? '<input type="hidden" name="status" value="'.e($result['status']).'">' : '',
            'q' => e($result['q']),
            'chips' => $chips,
            'body' => $body,
            'pagination' => $this->pagination($base, $filters, $result['total'], $result['page'], $result['per_page']),
        ]);

        return $this->panel(__('admin.history.title'), $this->settingsPage($request, 'history', $content), 'settings');
    }

    private function historyRow(Transaction $transaction): string
    {
        $raffle = $transaction->raffle;
        $group = AdminPaymentHistory::group($transaction->status);
        $tone = ['approved' => 'paid', 'pending' => 'pending', 'failed' => 'expired'][$group];

        return LegacyView::render('admin/modules/customers/settings/history_row', [
            'date' => e($transaction->created_at?->translatedFormat(__('common.datetime_format')) ?? '—'),
            'datetime' => e($transaction->created_at?->toIso8601String() ?? ''),
            'raffle' => $raffle
                ? '<a href="'.e(url('/admin/sortition/'.$raffle->id.'/listSortition')).'">'.e($raffle->title).'</a>'
                : e(__('admin.history.removed')),
            'gateway' => e($transaction->gateway === 'MP' ? 'Mercado Pago' : (string) ($transaction->gateway ?: '—')),
            'reference' => e((string) $transaction->gateway_reference),
            'amount' => e(Money::format(AdminPaymentHistory::amount($transaction), 'BRL')),
            'badge' => AdminUi::badge($tone, __('admin.history.status.'.$group)),
        ]);
    }
}
