<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o idioma da requisição, nesta ordem:
 *   1. ?lang=xx  (troca explícita pelo seletor; grava o cookie)
 *   2. cookie "locale"
 *   3. idioma salvo no cadastro do usuário logado (painel ou site)
 *   4. Accept-Language do navegador
 *   5. config('app.locale')
 *
 * Mantém as URLs atuais (/share/{codigo}) sem prefixo de idioma.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $explicit = self::normalize($request->query('lang'));

        $locale = $explicit
            ?? self::normalize($request->cookie(self::COOKIE))
            ?? self::normalize($request->user('admin')?->locale ?? $request->user('website')?->locale)
            ?? self::fromBrowser($request)
            ?? config('app.locale');

        app()->setLocale($locale);

        if ($explicit) {
            Cookie::queue(self::COOKIE, $explicit, 60 * 24 * 365);
        }

        return $next($request);
    }

    /** @return array<string, string> */
    public static function available(): array
    {
        return (array) config('rifa.locales', []);
    }

    /** Aceita "en", "en-US", "pt-br", "pt_BR"... e devolve o código suportado mais próximo. */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = str_replace('-', '_', trim($value));
        $available = array_keys(self::available());

        foreach ($available as $code) {
            if (strcasecmp($code, $value) === 0) {
                return $code;
            }
        }

        // Só o idioma ("pt" -> "pt_BR", "en_GB" -> "en")
        $language = strtolower(strtok($value, '_'));
        foreach ($available as $code) {
            if (strtolower(strtok($code, '_')) === $language) {
                return $code;
            }
        }

        return null;
    }

    private static function fromBrowser(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            if ($code = self::normalize($language)) {
                return $code;
            }
        }

        return null;
    }
}
