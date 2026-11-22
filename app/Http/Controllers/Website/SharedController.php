<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Admin\CheckoutController;
use App\Models\Customer;
use App\Models\Raffle;
use App\Services\MercadoPago;
use App\Support\LegacyView;
use App\Support\Media;
use App\Support\OgImage;
use App\Support\RaffleHelpers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

/**
 * Página pública da rifa: /share/{codigo}
 */
class SharedController extends SiteController
{
    public function show(Request $request, string $shareCode, MercadoPago $mp): RedirectResponse|string
    {
        $raffle = Raffle::query()->with('customer')->where('share_code', $shareCode)->firstOrFail();

        // Rifa ainda não liberada: mostra o pagamento da liberação
        if ($raffle->status === Raffle::STATUS_AWAITING_PAYMENT) {
            if ($mp->syncApprovedPayment($raffle)) {
                return redirect('/share/'.$raffle->share_code);
            }

            return $this->renderMasterShared(__('raffle.page_payment'), CheckoutController::paymentPage('website', $raffle, $mp, 'sortitionWebsite'));
        }

        if ($raffle->status === Raffle::STATUS_FINISHED) {
            return $this->renderMasterShared(__('raffle.page_draw'), $this->winnersPage($raffle));
        }

        return $this->renderMasterShared(e($raffle->title).' | Entre Amigos Rifa', $this->raffle($request, $raffle), $this->metas($raffle), $this->viteAssets());
    }

    private function raffle(Request $request, Raffle $raffle): string
    {
        $visitor = $this->visitor();
        $owner = $raffle->customer;
        $numbers = $raffle->numbersList();
        $qtd = max(1, $raffle->total_numbers);

        $reservedByMe = $visitor
            ? array_column(Raffle::filterBySituation(Raffle::filterByCustomer($numbers, $visitor->id), Raffle::RESERVED), 'number')
            : [];
        $totalPay = round($raffle->number_price * count($reservedByMe), 2);

        $taken = count(Raffle::filterBySituation($numbers, Raffle::UNAVAILABLE))
            + count(Raffle::filterBySituation($numbers, Raffle::RESERVED));
        $rules = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $raffle->draw_rules))));

        return LegacyView::render('website/shared/shared', [
            'titleSortition' => e($raffle->title),
            'description' => $raffle->description,
            'price' => $raffle->formatMoney($raffle->number_price),
            'shared' => $raffle->share_code,
            'date_lottery' => RaffleHelpers::dateLottery($raffle),
            'date_countdown' => $this->drawCountdown($raffle),
            'message' => $this->status($request),
            'phone_whatsapp' => '+55'.$owner->phone,
            'name_customer' => e($visitor?->shortName() ?? ''),
            'nameProprietary' => e($owner->name),
            'imageProfileProprietary' => $owner->photoUrl(),
            'pageSocial' => RaffleHelpers::socialLinks($owner),
            'slides' => $this->slides($raffle),
            'soldPercent' => (int) floor($taken * 100 / $qtd),
            'qtdNumbers' => $raffle->total_numbers,
            'availableNumbers' => count(Raffle::filterBySituation($numbers, Raffle::AVAILABLE)),
            'draw_rules' => $raffle->draw_rules,
            'rulesSummary' => e(Str::limit($rules, 180)),
            'rulesHidden' => $rules === '' && trim(strip_tags((string) $raffle->description)) === '' ? 'd-none' : '',
            // Pix da reserva já existente (atualizado via AJAX após cada nova reserva)
            'pagePaymentPix' => $reservedByMe ? RaffleHelpers::ownerPix($owner, $totalPay, implode(',', $reservedByMe)) : '',
            'raffleData' => $this->raffleData($raffle, $numbers, $visitor),
        ]);
    }

    /** "em X dias" / "hoje" / "amanhã" até o sorteio; vazio se sem data ou já passou. */
    private function drawCountdown(Raffle $raffle): string
    {
        if (! $raffle->draw_date) {
            return '';
        }

        $days = (int) now()->startOfDay()->diffInDays($raffle->draw_date->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => '',
            $days === 0 => __('raffle.countdown_today'),
            $days === 1 => __('raffle.countdown_tomorrow'),
            default => __('raffle.countdown_days', ['count' => $days]),
        };
    }

    /** Slides da galeria do prêmio (Swiper 4:5 + GLightbox). */
    private function slides(Raffle $raffle): string
    {
        $html = '';
        foreach ($raffle->photosList() as $key => $path) {
            $html .= LegacyView::render('website/shared/photo_slide', [
                'local' => Media::url($path),
                'alt' => e(__('raffle.photo_alt', ['title' => $raffle->title, 'number' => $key + 1])),
                'loading' => $key === 0 ? 'eager' : 'lazy',
            ]);
        }

        return $html !== '' ? $html : LegacyView::render('website/shared/photo_slide', [
            'local' => url('storage/images/system/favicon.ico'),
            'alt' => e($raffle->title),
            'loading' => 'eager',
        ]);
    }

    /**
     * Estado compacto da grade para o front (window.__RAFFLE__):
     * status = um caractere por número, na ordem (D disponível, R reservado, U pago/indisponível).
     */
    private function raffleData(Raffle $raffle, array $numbers, ?Customer $visitor): string
    {
        $codes = [Raffle::AVAILABLE => 'D', Raffle::RESERVED => 'R', Raffle::UNAVAILABLE => 'U'];
        usort($numbers, fn ($a, $b) => $a->number <=> $b->number);

        $status = $mineReserved = $minePaid = [];
        foreach ($numbers as $number) {
            $status[] = $codes[$number->situation] ?? 'U';
            if ($visitor && $number->customer_id == $visitor->id) {
                if ($number->situation === Raffle::RESERVED) {
                    $mineReserved[] = $number->number;
                } elseif ($number->situation === Raffle::UNAVAILABLE) {
                    $minePaid[] = $number->number;
                }
            }
        }

        $data = [
            'shareCode' => $raffle->share_code,
            'price' => (float) $raffle->number_price,
            'currency' => $raffle->currency ?: config('rifa.default_currency'),
            'locale' => str_replace('_', '-', app()->getLocale()),
            'i18n' => trans('raffle_js'),
            'total' => (int) $raffle->total_numbers,
            'status' => implode('', $status),
            'mine' => ['reserved' => $mineReserved, 'paid' => $minePaid],
            'drawDate' => RaffleHelpers::dateLottery($raffle),
            'customer' => $visitor ? ['id' => $visitor->id, 'name' => $visitor->shortName()] : null,
            'reservationMinutes' => Raffle::RESERVATION_MINUTES,
            'endpoints' => [
                'finish' => url('/checkoutFinish/'.$raffle->share_code),
                'myNumbers' => url('/checkout/'.$raffle->share_code),
                'reserve' => url('/api/v1/reserveNumbers/'.$raffle->share_code),
                'register' => url('/customer/fastRegister'),
                'mercadoPago' => MercadoPago::ownerToken($raffle->customer) !== '' ? url('/getMercadoPagoCheckOut/'.$raffle->share_code) : null,
            ],
        ];

        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }

    /** Tags do Vite (CSS/JS isolados da página da rifa); vazio se não houver build nem dev server. */
    private function viteAssets(): string
    {
        if (! is_file(public_path('hot')) && ! is_file(public_path('build/manifest.json'))) {
            return '';
        }

        return Vite::__invoke(['resources/css/raffle.css', 'resources/js/raffle.js'])->toHtml();
    }

    private function winnersPage(Raffle $raffle): string
    {
        $items = '';
        $default = url('storage/images/profile/profile_default.png');

        foreach ($raffle->winnersList() as $winner) {
            // Colaboradores do cadastro rápido não têm e-mail: procura também pelo telefone
            $customer = match (true) {
                ! empty($winner->email) => Customer::query()->where('email', $winner->email)->first(),
                ! empty($winner->phone) => Customer::query()->where('phone', $winner->phone)->first(),
                default => null,
            };

            $items .= LegacyView::render('website/shared/customers/winners', [
                'number' => $winner->number,
                'name_winner' => e($winner->name ?: __('raffle.no_winner')),
                'photo' => $customer ? $customer->photoUrl() : $default,
            ]);
        }

        return LegacyView::render('website/shared/winner', ['pageItensWinners' => $items, 'title' => e($raffle->title)]);
    }

    /** Meta OG/Twitter: foto do prêmio + preço e data do sorteio no texto da prévia. */
    private function metas(Raffle $raffle): array
    {
        $description = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $raffle->description))));
        $price = $raffle->formatMoney($raffle->number_price);
        $offer = __('raffle.og_offer', ['price' => $price, 'date' => RaffleHelpers::dateLottery($raffle)]);
        $photo = $raffle->photosList()[0] ?? null;

        $numbers = $raffle->numbersList();
        $taken = count(Raffle::filterBySituation($numbers, Raffle::UNAVAILABLE))
            + count(Raffle::filterBySituation($numbers, Raffle::RESERVED));
        $card = OgImage::url($raffle, (int) floor($taken * 100 / max(1, $raffle->total_numbers)));

        return [
            'meta_title' => $raffle->title.' · '.$price,
            'meta_description' => Str::limit($description !== '' ? $offer.'. '.$description : $offer, 200),
            'meta_image' => $card ?? ($photo ? Media::url($photo) : url('storage/images/system/favicon.ico')),
            'meta_image_size' => $card
                ? '<meta property="og:image:width" content="1200" />'
                    .'<meta property="og:image:height" content="630" />'
                    .'<meta property="og:image:type" content="image/jpeg" />'
                : '',
            'meta_image_alt' => $raffle->title,
            'meta_url' => url('/share/'.$raffle->share_code),
        ];
    }

    private function status(Request $request): string
    {
        return $this->statusMessage($request, [
            'created' => ['success', __('raffle.status_created')],
            'invalid_phone' => ['danger', __('raffle.status_invalid_phone')],
            'not_register_customer_fast' => ['warning', __('raffle.status_not_registered')],
            'not_selected_number' => ['warning', __('raffle.status_not_selected')],
        ]);
    }

    /** Lista simples dos números de um colaborador. */
    public function numbersOfCustomer(string $shareCode, int $idCustomer): string
    {
        $raffle = Raffle::query()->where('share_code', $shareCode)->firstOrFail();

        $html = '';
        foreach (Raffle::filterByCustomer($raffle->numbersList(), $idCustomer) as $number) {
            $html .= LegacyView::render('website/checkout/list', ['number' => $number->number, 'css' => '', 'category' => '']);
        }

        return $this->renderMaster('Entre Amigos', $html, 'index');
    }
}
