<?php

namespace App\Http\Controllers\Website;

use App\Models\Raffle;
use App\Services\MercadoPago;
use App\Support\LegacyView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pagamento dos números pelo colaborador, via Mercado Pago do dono da rifa.
 */
class CheckoutController extends SiteController
{
    public function mercadoPago(string $shareCode): RedirectResponse
    {
        $raffle = Raffle::query()->with('customer')->where('share_code', $shareCode)->firstOrFail();
        $visitorId = $this->visitor()->id;

        $reserved = array_filter(
            Raffle::filterByCustomer($raffle->numbersList(), $visitorId),
            fn ($n) => $n->situation === Raffle::RESERVED
        );

        if (! $reserved) {
            return redirect('/share/'.$shareCode.'?status=not_selected_number');
        }

        $checkout = MercadoPago::forToken(MercadoPago::ownerToken($raffle->customer))->checkoutProForNumbers(
            shared: $shareCode,
            customerId: $visitorId,
            price: $raffle->number_price,
            quantity: count($reserved),
            description: implode(',', array_column($reserved, 'number')),
        );

        return $checkout['link']
            ? redirect()->away($checkout['link'])
            : redirect('/share/'.$shareCode.'?status=not_selected_number');
    }

    /** Retorno do Mercado Pago do dono da rifa e página de agradecimento. */
    public function finish(Request $request, string $shareCode): RedirectResponse|string
    {
        $status = $request->query('status_sys');

        if ($status === 'mp_failure') {
            return redirect('/share/'.$shareCode.'?status=not_selected_number');
        }

        if (in_array($status, ['mp_success', 'mp_pending'], true)) {
            $raffle = Raffle::query()->with('customer')->where('share_code', $shareCode)->firstOrFail();
            $customerId = (int) $request->query('customer_id');

            // Só libera os números se o pagamento estiver realmente aprovado na conta do dono
            $payment = MercadoPago::forToken(MercadoPago::ownerToken($raffle->customer))->getPayment((string) $request->query('payment_id'));

            if (($payment['status'] ?? null) === 'approved') {
                $numbers = $raffle->numbersList();
                foreach ($numbers as $number) {
                    if ($number->customer_id == $customerId && $number->situation === Raffle::RESERVED) {
                        $number->pay = 'S';
                        $number->situation = Raffle::UNAVAILABLE;
                    }
                }
                $raffle->setNumbersList($numbers);
                $raffle->save();
            }

            return redirect('/checkoutFinish/'.$shareCode.'?type_payment=mp');
        }

        if ($request->query('type_payment') === 'mp') {
            return LegacyView::render('website/payments/finish_payment', [
                'shared' => $shareCode,
                'title' => 'Obrigado pela ajuda',
                'text' => 'Muito obrigado pela colaboração, aguarde o sorteio e acompanhe a rifa pelo mesmo link de compra, boa sorte!',
            ]);
        }

        return LegacyView::render('website/payments/finish_payment', [
            'shared' => $shareCode,
            'title' => '<strong>Entre em Contato</strong> com o dono da rifa para confirmar o pagamento.',
            'text' => 'Nesse método de pagamento a conferência e a liberação dos números escolhidos pelo colaborador são conferidos manualmente pelo dono da rifa; caso haja demora na liberação dos números escolhidos entre em contato com o dono da rifa.',
        ]);
    }
}
