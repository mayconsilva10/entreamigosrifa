<?php

namespace App\Http\Controllers\Admin;

use App\Models\PlatformSetting;
use App\Models\Raffle;
use App\Services\MercadoPago;
use App\Support\AdminUi;
use App\Support\LegacyView;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Pagamento que o dono faz à plataforma para liberar a rifa (status 1 -> 2).
 * Tela do painel: resumo (números × tarifa), Pix com QR + copia-e-cola, Checkout Pro e verificação do status (polling leve).
 */
class CheckoutController extends PanelController
{
    /** Moeda da tarifa de liberação (cobrada pelo Mercado Pago em BRL). */
    private const FEE_CURRENCY = 'BRL';

    public function index(int $raffleId, MercadoPago $mp): RedirectResponse|string
    {
        $raffle = $this->ownedRaffle($raffleId);

        if (! $raffle || $raffle->status !== Raffle::STATUS_AWAITING_PAYMENT) {
            return redirect('/admin/sortition');
        }

        if ($mp->syncApprovedPayment($raffle)) {
            return redirect('/admin/sortition?status=mp_approved');
        }

        $pix = $mp->pixForRaffle($raffle);
        $checkout = $mp->checkoutProForRaffle($raffle, 'adminPanel', ['link_share' => $raffle->share_code]);
        $fee = PlatformSetting::pricePerNumber();
        $expiresAt = $pix['expires_at'] ? Carbon::parse($pix['expires_at']) : null;
        $hasPix = $pix['qr_code'] !== '';

        $breadcrumb = '<nav class="adm-breadcrumb" aria-label="'.e(__('admin.manage.breadcrumb')).'">'
            .'<a href="'.e(url('/admin/sortition')).'">'.e(__('admin.nav.raffles')).'</a>'
            .'<i class="bi bi-chevron-right" aria-hidden="true"></i><span aria-current="page">'.e($raffle->title).'</span></nav>';

        $content = LegacyView::render('admin/modules/checkout/index', [
            'header' => LegacyView::render('admin/components/page_header', [
                'breadcrumb' => $breadcrumb,
                'title' => e(__('admin.checkout.title')),
                'subtitle' => '<p class="adm-page-header__subtitle">'.e(__('admin.checkout.subtitle')).'</p>',
                'actions' => '',
            ]),
            'data' => json_encode([
                'statusUrl' => url('/admin/'.$raffle->id.'/checkout/status'),
                'expiresAt' => $expiresAt?->toIso8601String(),
                'poll' => $hasPix,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'not_configured' => $mp->configured() ? '' : LegacyView::render('admin/components/notice', [
                'tone' => 'pending',
                'role' => 'status',
                'icon' => 'bi-exclamation-triangle-fill',
                'message' => e(__('admin.checkout.not_configured')),
            ]),
            'title_raffle' => e($raffle->title),
            'qtd_numbers' => AdminUi::integer((int) $raffle->total_numbers),
            'fee' => e(Money::format($fee, self::FEE_CURRENCY)),
            'total_pay' => e(Money::format($pix['amountTot'], self::FEE_CURRENCY)),
            'badge_pending' => AdminUi::badge('pending', __('admin.checkout.state.pending')),
            'badge_paid' => AdminUi::badge('paid', __('admin.checkout.state.paid')),
            'badge_expired' => AdminUi::badge('expired', __('admin.checkout.state.expired')),
            'pix' => $hasPix
                ? LegacyView::render('admin/payments/mercado_pago/pix', [
                    'qr_image' => e($pix['qr_code_base64']),
                    'qr_code' => e($pix['qr_code']),
                    'expires' => $expiresAt
                        ? e(__('admin.checkout.pix.expires', ['time' => $expiresAt->copy()->setTimezone(config('app.timezone'))->translatedFormat(__('common.datetime_format'))]))
                        : '',
                ])
                : '<p class="adm-muted-note">'.e(__('admin.checkout.pix.unavailable')).'</p>',
            'checkout_pro' => $checkout['id_preference']
                ? LegacyView::render('admin/payments/mercado_pago/checkout_pro', [
                    'mp_public_key' => e((string) $checkout['public_key']),
                    'id_preference' => e((string) $checkout['id_preference']),
                    'mp_locale' => str_starts_with(app()->getLocale(), 'en') ? 'en-US' : 'pt-BR',
                ])
                : '<p class="adm-muted-note">'.e(__('admin.checkout.card.unavailable')).'</p>',
        ]);

        return $this->panel(__('admin.title.payment'), $content, 'sortition');
    }

    /** Verificação do pagamento (botão "Já paguei" e polling): {status: paid|pending, redirect?}. */
    public function status(int $raffleId, MercadoPago $mp): JsonResponse
    {
        $raffle = $this->ownedRaffle($raffleId);
        if (! $raffle) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $paid = $raffle->status !== Raffle::STATUS_AWAITING_PAYMENT || $mp->syncApprovedPayment($raffle);

        return response()->json([
            'status' => $paid ? 'paid' : 'pending',
            'message' => __($paid ? 'admin.raffles.flash.mp_approved' : 'admin.checkout.state.still_pending'),
            'redirect' => $paid ? url('/admin/sortition?status=mp_approved') : null,
        ]);
    }

    /** Tela de pagamento da liberação na página pública (/share/{code} de rifa ainda não liberada). */
    public static function paymentPage(string $side, Raffle $raffle, MercadoPago $mp, string $redirectPage): string
    {
        $pix = $mp->pixForRaffle($raffle);
        $checkout = $mp->checkoutProForRaffle($raffle, $redirectPage, ['link_share' => $raffle->share_code]);

        $notConfigured = $mp->configured() ? '' : '<div class="alert alert-warning">Mercado Pago não configurado: defina MP_ACCESS_TOKEN e MP_PUBLIC_KEY no .env.</div>';

        return $notConfigured.LegacyView::render('website/payments/pending', [
            'mp_checkout_pro' => LegacyView::render('website/payments/mercado_pago/checkout_pro', ['id_preference' => (string) $checkout['id_preference']]),
            'mp_page_pix' => LegacyView::render('website/payments/mercado_pago/pix', [
                'mpcodeQrCode' => $pix['qr_code'],
                'mp_qr_image' => $pix['qr_code_base64'],
            ]),
            'mp_public_key' => (string) $checkout['public_key'],
            'title_sortition' => e($raffle->title),
            'qtd_numbers' => $raffle->total_numbers,
            'total_pay' => Raffle::money($pix['amountTot']),
        ]);
    }
}
