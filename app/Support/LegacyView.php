<?php

namespace App\Support;

/**
 * Motor de templates herdado do sistema antigo.
 *
 * Os arquivos ficam em resources/templates/{tema}/... e usam placeholders {{variavel}}
 * (sem PHP). Isso permite migrar as telas para Blade aos poucos sem reescrever tudo.
 *
 * Além da substituição de variáveis, injeta automaticamente:
 *  - <input name="_token"> em todo <form method="post">
 *  - <meta name="csrf-token"> + jQuery.ajaxSetup antes do </head>
 *
 * Internacionalização (lang/{locale}/*.php):
 *  - {{t:grupo.chave}}   texto traduzido, escapado para HTML
 *  - {{t!:grupo.chave}}  texto traduzido sem escape (para traduções que contêm HTML)
 *  - {{LANG}}            idioma atual no formato HTML (pt-BR, en)
 *  - {{LOCALE_SWITCHER}} links para trocar de idioma
 * Traduções podem conter {{variavel}}: são resolvidas antes das variáveis da view.
 */
class LegacyView
{
    public static function render(string $view, array $vars = []): string
    {
        $file = resource_path('templates/'.config('rifa.theme').'/'.ltrim($view, '/').'.php');
        $content = is_file($file) ? file_get_contents($file) : '';

        $content = self::injectCsrf($content);
        $content = self::translate($content);

        $vars = array_merge([
            'URL' => rtrim(url('/'), '/'),
            'LANG' => str_replace('_', '-', app()->getLocale()),
        ], $vars);

        if (str_contains($content, '{{LOCALE_SWITCHER}}')) {
            $vars['LOCALE_SWITCHER'] = self::localeSwitcher();
        }

        $keys = array_map(fn ($key) => '{{'.$key.'}}', array_keys($vars));
        $values = array_map(fn ($value) => (string) $value, array_values($vars));

        return str_replace($keys, $values, $content);
    }

    private static function translate(string $content): string
    {
        if (! str_contains($content, '{{t')) {
            return $content;
        }

        return preg_replace_callback(
            '/\{\{t(!?):([A-Za-z0-9_.\-]+)\}\}/',
            fn (array $match) => $match[1] === '!' ? (string) __($match[2]) : e(__($match[2])),
            $content
        );
    }

    /**
     * Botão compacto (globo + sigla "PT"/"EN") que abre um menu com os idiomas.
     * Usa <details> (funciona sem JS); o raffle.js só fecha o menu ao clicar fora / Esc.
     * Siglas em vez de bandeiras: idioma não é país (en vale p/ EUA e Reino Unido) e
     * emojis de bandeira não aparecem no Windows.
     */
    public static function localeSwitcher(): string
    {
        $current = app()->getLocale();
        $locales = (array) config('rifa.locales', []);
        $short = fn (string $code) => strtoupper((string) strtok($code, '_'));
        $items = '';

        foreach ($locales as $code => $name) {
            $hreflang = str_replace('_', '-', $code);
            $badge = '<span class="rf-locale__code" aria-hidden="true">'.$short($code).'</span>';

            $items .= $code === $current
                ? '<li><span class="rf-locale__item is-current" aria-current="true" lang="'.$hreflang.'">'.$badge.e($name)
                    .'<i class="bi bi-check-lg rf-locale__check" aria-hidden="true"></i></span></li>'
                : '<li><a class="rf-locale__item" href="'.e(request()->fullUrlWithQuery(['lang' => $code])).'" hreflang="'.$hreflang.'" lang="'.$hreflang.'" rel="nofollow">'
                    .$badge.e($name).'</a></li>';
        }

        $label = e(__('common.language').': '.($locales[$current] ?? $current));

        return '<details class="rf-locale">'
            .'<summary class="rf-locale__toggle" aria-label="'.$label.'" title="'.$label.'">'
            .'<i class="bi bi-globe2" aria-hidden="true"></i><span aria-hidden="true">'.$short($current).'</span>'
            .'<i class="bi bi-chevron-down rf-locale__chevron" aria-hidden="true"></i>'
            .'</summary>'
            .'<ul class="rf-locale__menu">'.$items.'</ul>'
            .'</details>';
    }

    private static function injectCsrf(string $content): string
    {
        $token = csrf_token();

        $content = preg_replace(
            '/(<form\b[^>]*\bmethod\s*=\s*["\']?post["\']?[^>]*>)/i',
            '$1<input type="hidden" name="_token" value="'.$token.'">',
            $content
        );

        $headSnippet = '<meta name="csrf-token" content="'.$token.'">'
            .'<script>document.addEventListener("DOMContentLoaded",function(){if(window.jQuery){jQuery.ajaxSetup({headers:{"X-CSRF-TOKEN":"'.$token.'"}});}});</script>';

        return preg_replace('/<\/head>/i', $headSnippet.'</head>', $content, 1);
    }
}
