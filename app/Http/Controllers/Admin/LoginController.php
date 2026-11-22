<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Support\LegacyView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Throwable;

class LoginController extends PanelController
{
    /** Login + cadastro (abas), tela dividida com a marca. ?tab=register abre o cadastro. */
    public function show(Request $request, string $errorMessage = ''): string
    {
        $status = $errorMessage !== '' ? $this->danger(e($errorMessage)) : $this->statusMessage($request, [
            'created' => ['success', e(__('admin.auth.flash.created'))],
            'duplicated' => ['danger', e(__('admin.auth.flash.duplicated'))],
            'invalid_user' => ['danger', e(__('admin.auth.flash.invalid_user'))],
        ]);

        $tab = $request->query('tab') === 'register' || $request->query('status') === 'duplicated' ? 'register' : 'login';

        $content = LegacyView::render('admin/modules/register/form', [
            'tab' => $tab,
            'status' => $status,
            'brand' => LegacyView::render('admin/modules/register/brand'),
            'formLogin' => LegacyView::render('admin/modules/register/login', ['email' => e((string) $request->input('email', ''))]),
            'formRegister' => LegacyView::render('admin/modules/register/register'),
        ]);

        return $this->renderMaster(__('admin.auth.title'), $content);
    }

    public function login(Request $request): RedirectResponse|string
    {
        $customer = Customer::query()->where('email', (string) $request->input('email'))->first();

        if (! $customer || ! $customer->password || ! Hash::check((string) $request->input('password'), $customer->password)) {
            return $this->show($request, __('admin.auth.flash.invalid_user'));
        }

        Auth::guard('admin')->login($customer);
        $request->session()->regenerate();

        return redirect('/admin');
    }

    /** A tela de login não usa o shell do painel (sem toasts): aviso inline. $message já escapada. */
    protected function alert(string $type, string $message): string
    {
        return LegacyView::render('admin/components/notice', [
            'tone' => $type === 'success' ? 'paid' : 'danger',
            'role' => $type === 'success' ? 'status' : 'alert',
            'icon' => $type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-octagon-fill',
            'message' => $message,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        return redirect('/admin/login');
    }

    /** Login com o botão "Entrar com Google" (Google Identity Services). */
    public function loginGoogle(Request $request): RedirectResponse|string
    {
        $credential = (string) $request->input('credential');
        $csrf = (string) $request->input('g_csrf_token');

        if ($credential === '' || $csrf === '' || $csrf !== (string) $request->cookie('g_csrf_token')) {
            return redirect('/admin/login');
        }

        try {
            $payload = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $credential])->json();
        } catch (Throwable) {
            $payload = null;
        }

        if (empty($payload['email']) || ($payload['aud'] ?? null) !== config('services.google.client_id')) {
            return $this->show($request, __('admin.auth.flash.google_error'));
        }

        $customer = Customer::query()->firstOrCreate(
            ['email' => $payload['email']],
            ['name' => $payload['name'] ?? $payload['email'], 'photo' => $payload['picture'] ?? null]
        );

        Auth::guard('admin')->login($customer);
        $request->session()->regenerate();

        return redirect('/admin');
    }

    public function accountRecovery(): string
    {
        $content = LegacyView::render('admin/modules/register/account-recovery', [
            'brand' => LegacyView::render('admin/modules/register/brand'),
            'support_url' => e((string) config('rifa.support_url')),
        ]);

        return $this->renderMaster(__('admin.auth.recovery.title'), $content);
    }

    /** Cadastro pelo formulário da tela de login (POST /newCustomer). */
    public function register(Request $request): RedirectResponse
    {
        $email = trim((string) $request->input('email'));
        $name = trim((string) $request->input('username'));
        $phone = Customer::onlyDigitsPhone($request->input('phone'));
        $password = Hash::make((string) $request->input('password'));

        if ($email === '' || $name === '' || Customer::query()->where('email', $email)->exists()) {
            return redirect('/admin/login?status=duplicated');
        }

        // Colaborador que já se cadastrou só com telefone: completa o cadastro
        $existing = $phone !== '' ? Customer::query()->where('phone', $phone)->first() : null;
        if ($existing) {
            if (! empty($existing->email)) {
                return redirect('/admin/login?status=duplicated');
            }

            $existing->update(['email' => $email, 'name' => $name, 'password' => $password]);

            return redirect('/admin/login?status=created');
        }

        Customer::query()->create(['email' => $email, 'name' => $name, 'phone' => $phone, 'password' => $password]);

        return redirect('/admin/login?status=created');
    }
}
