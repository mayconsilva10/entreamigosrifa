<?php

namespace App\Support;

use NumberFormatter;

/**
 * Formata valores monetários conforme o idioma atual e a moeda (ISO 4217).
 *
 * Usa NumberFormatter (extensão intl) quando disponível; senão, uma tabela própria
 * que cobre os idiomas e moedas configurados em config/rifa.php.
 */
class Money
{
    /** Símbolos usados no fallback sem intl. */
    private const SYMBOLS = ['BRL' => 'R$', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];

    /**
     * Separadores e posição do símbolo por idioma (fallback sem intl).
     * [decimal, milhar, padrão] — %s = símbolo, %v = valor.
     */
    private const FORMATS = [
        'pt' => [',', '.', '%s %v'],
        'en' => ['.', ',', '%s%v'],
        'es' => [',', '.', '%v %s'],
        'de' => [',', '.', '%v %s'],
        'fr' => [',', ' ', '%v %s'],
        'it' => [',', '.', '%v %s'],
    ];

    public static function format(float $amount, ?string $currency = null, ?string $locale = null): string
    {
        $currency = strtoupper($currency ?: (string) config('rifa.default_currency', 'BRL'));
        $locale ??= app()->getLocale();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);
            if ($formatted !== false) {
                // NBSP -> espaço comum (evita surpresas em comparações e no JS)
                return str_replace(["\u{00A0}", "\u{202F}"], ' ', $formatted);
            }
        }

        [$decimal, $thousands, $pattern] = self::FORMATS[strtolower(strtok($locale, '_-'))] ?? self::FORMATS['en'];
        $value = number_format(abs($amount), 2, $decimal, $thousands);
        $symbol = self::SYMBOLS[$currency] ?? $currency.' ';

        return ($amount < 0 ? '-' : '').trim(str_replace(['%s', '%v'], [$symbol, $value], $pattern));
    }

    /**
     * Separadores [decimal, milhar] do idioma (máscara de preço no painel).
     *
     * @return array{0: string, 1: string}
     */
    public static function separators(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        [$decimal, $thousands] = self::FORMATS[strtolower(strtok($locale, '_-'))] ?? self::FORMATS['en'];

        return [$decimal, $thousands];
    }

    /**
     * Valor digitado/mascarado → número, aceitando os formatos dos dois idiomas:
     * "R$ 1.234,56", "1,234.56", "$1234.56", "10,5", "10". Com os dois separadores, o último é o decimal;
     * com um só, ele é decimal a menos que separe grupos de 3 dígitos ("1.000" = mil). Vazio/inválido → null.
     */
    public static function parse(?string $value): ?float
    {
        $clean = preg_replace('/[^\d,.\-]/', '', (string) $value);
        if (! preg_match('/\d/', $clean)) {
            return null;
        }

        $negative = str_starts_with($clean, '-');
        $clean = str_replace('-', '', $clean);
        $comma = strrpos($clean, ',');
        $dot = strrpos($clean, '.');

        $decimal = null;
        if ($comma !== false && $dot !== false) {
            $decimal = $comma > $dot ? ',' : '.';
        } elseif ($comma !== false || $dot !== false) {
            $separator = $comma !== false ? ',' : '.';
            $parts = explode($separator, $clean);
            $decimal = count($parts) === 2 && strlen($parts[1]) !== 3 ? $separator : null;
        }

        if ($decimal === null) {
            $number = (float) preg_replace('/\D/', '', $clean);
        } else {
            $position = strrpos($clean, $decimal);
            $integer = preg_replace('/\D/', '', substr($clean, 0, $position));
            $fraction = preg_replace('/\D/', '', substr($clean, $position + 1));
            $number = (float) (($integer === '' ? '0' : $integer).'.'.($fraction === '' ? '0' : $fraction));
        }

        return round($negative ? -$number : $number, 2);
    }

    /** Símbolo da moeda (para máscaras de input). */
    public static function symbol(?string $currency = null): string
    {
        $currency = strtoupper($currency ?: (string) config('rifa.default_currency', 'BRL'));

        return self::SYMBOLS[$currency] ?? $currency;
    }
}
