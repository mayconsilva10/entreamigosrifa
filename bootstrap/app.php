<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Visitante sem login: painel vai para /admin/login, site vai para a home
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin*') ? '/admin/login' : '/');
        $middleware->redirectUsersTo('/admin');

        // Chamadas externas (Mercado Pago, Google Identity, clientes da API JWT)
        $middleware->validateCsrfTokens(except: [
            'admin/payments/notificationMp',
            'admin/login-google',
            'api/v1/auth',
        ]);

        // Cookie criado pelo script do Google (não é criptografado pelo Laravel)
        $middleware->encryptCookies(except: ['g_csrf_token']);

        // Idioma da requisição (?lang, cookie, cadastro, navegador)
        $middleware->web(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
