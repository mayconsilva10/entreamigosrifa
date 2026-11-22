<?php

namespace App\Http\Controllers;

use App\Models\Raffle;
use App\Services\MercadoPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Retorno do Checkout Pro (GET) e webhook (POST) da conta Mercado Pago da plataforma.
 */
class MercadoPagoController extends Controller
{
    /** O cliente volta do Checkout Pro com ?status=...&payment_id=...&external_reference=... */
    public function back(Request $request, MercadoPago $mp): RedirectResponse|string
    {
        $approved = false;
        $raffle = Raffle::query()->find((int) $request->query('external_reference'));
        $paymentId = (string) $request->query('payment_id', '');

        // Confere o pagamento direto na API, em vez de confiar na query string
        if ($raffle && $paymentId !== '') {
            $payment = $mp->getPayment($paymentId);
            if (($payment['status'] ?? null) === 'approved' && (string) ($payment['external_reference'] ?? '') === (string) $raffle->id) {
                MercadoPago::approveRaffle($raffle, $paymentId, $request->query());
                $approved = true;
            }
        }

        return match ($request->query('redirectPage')) {
            'adminPanel' => redirect('/admin/sortition?status='.($approved ? 'mp_approved' : 'mp_not_approved')),
            'sortitionWebsite' => redirect('/share/'.$request->query('link_share')),
            default => '<h1>Entre em contato com o suporte pelo WhatsApp</h1>',
        };
    }

    /** Notificação (webhook) do Mercado Pago. */
    public function webhook(Request $request, MercadoPago $mp): Response
    {
        $type = $request->input('type', $request->input('topic'));
        $id = $request->input('data.id', $request->input('id'));

        Log::info('Webhook Mercado Pago', $request->all());

        if ($type === 'payment' && $id) {
            $payment = $mp->getPayment($id);
            $raffle = Raffle::query()->find((int) ($payment['external_reference'] ?? 0));

            if ($raffle && ($payment['status'] ?? null) === 'approved' && $raffle->status === Raffle::STATUS_AWAITING_PAYMENT) {
                MercadoPago::approveRaffle($raffle, (string) $id, $payment['transaction_details'] ?? []);
            }
        }

        return response('', 200);
    }
}
