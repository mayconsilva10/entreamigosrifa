<?php

namespace App\Http\Controllers\Website;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cadastro/login rápido do colaborador pelo telefone, na página da rifa.
 *
 * Formulário legado: redireciona de volta para /share/{codigo}.
 * AJAX (drawer do checkout, expectsJson): responde JSON em duas etapas,
 * "Telefone" -> (se o telefone for novo) "Nome".
 */
class CustomerController extends SiteController
{
    public function fastRegister(Request $request): RedirectResponse|JsonResponse
    {
        $phone = Customer::onlyDigitsPhone($request->input('phone'));
        $name = trim((string) $request->input('username'));
        $codeShare = (string) $request->input('codeShare');
        $wantsJson = $request->expectsJson();

        if (strlen($phone) < 10) {
            return $wantsJson
                ? response()->json(['message' => __('raffle.invalid_phone'), 'errors' => ['phone' => [__('raffle.invalid_phone')]]], 422)
                : redirect('/share/'.$codeShare.'?status=invalid_phone');
        }

        $customer = Customer::query()->where('phone', $phone)->first();
        $created = false;

        if (! $customer) {
            if ($wantsJson && $name === '') {
                return response()->json(['message' => __('raffle.ask_name'), 'needs_name' => true, 'errors' => ['username' => [__('raffle.name_required')]]], 422);
            }

            $customer = Customer::query()->create([
                'name' => mb_substr($name, 0, 255) ?: __('raffle.default_customer_name'),
                'phone' => $phone,
                'locale' => app()->getLocale(),
            ]);
            $created = true;
        }

        Auth::guard('website')->login($customer);
        $request->session()->regenerate();

        if ($wantsJson) {
            return response()->json([
                'created' => $created,
                'customer' => ['id' => $customer->id, 'name' => $customer->shortName()],
                'csrf_token' => csrf_token(),
            ], $created ? 201 : 200);
        }

        return redirect('/share/'.$codeShare.($created ? '?status=created' : ''));
    }

    public function logout(): RedirectResponse
    {
        Auth::guard('website')->logout();

        return redirect('/');
    }
}
