<?php

namespace App\Support;

use App\Models\Raffle;
use Illuminate\Support\Carbon;

/**
 * Mapeamento único status → badge do painel (classe de tom, ícone Bootstrap Icons e rótulo traduzido).
 *
 * Tons (tokens em resources/css/admin.css): paid, pending, expired, winner, available, danger, neutral.
 * Status de bilhete: paid | pending | expired | winner | available.
 * Status de rifa: active | awaiting | finished | drawn.
 * Nunca só cor: todo badge leva ícone + texto.
 */
final class AdminUi
{
    /** @var array<string, array{tone: string, icon: string}> */
    private const BADGES = [
        // Bilhetes / pedidos
        'paid' => ['tone' => 'paid', 'icon' => 'bi-check-circle-fill'],
        'pending' => ['tone' => 'pending', 'icon' => 'bi-hourglass-split'],
        'expired' => ['tone' => 'expired', 'icon' => 'bi-clock-history'],
        'winner' => ['tone' => 'winner', 'icon' => 'bi-trophy-fill'],
        'available' => ['tone' => 'available', 'icon' => 'bi-circle'],
        // Rifas
        'active' => ['tone' => 'paid', 'icon' => 'bi-broadcast'],
        'awaiting' => ['tone' => 'pending', 'icon' => 'bi-hourglass-split'],
        'finished' => ['tone' => 'expired', 'icon' => 'bi-flag-fill'],
        'drawn' => ['tone' => 'winner', 'icon' => 'bi-trophy-fill'],
    ];

    private const FALLBACK = ['tone' => 'neutral', 'icon' => 'bi-question-circle'];

    /**
     * @return array{status: string, tone: string, icon: string, label: string}
     */
    public static function badgeData(string $status): array
    {
        $known = isset(self::BADGES[$status]);
        $badge = self::BADGES[$status] ?? self::FALLBACK;

        return [
            'status' => $known ? $status : 'unknown',
            'tone' => $badge['tone'],
            'icon' => $badge['icon'],
            'label' => __('admin.status.'.($known ? $status : 'unknown')),
        ];
    }

    /** HTML do badge (partial admin/components/badge). Rótulo opcional sobrescreve o padrão. */
    public static function badge(string $status, ?string $label = null): string
    {
        $data = self::badgeData($status);

        return LegacyView::render('admin/components/badge', [
            'tone' => $data['tone'],
            'icon' => $data['icon'],
            'label' => e($label ?? $data['label']),
            'status' => $data['status'],
        ]);
    }

    /**
     * HTML do KPI (partial admin/components/kpi). Textos já devem vir escapados.
     *
     * @param  array{label: string, value: string, icon: string, raw?: float|int, format?: string, currency?: string, hint?: string, tone?: string, action?: string}  $kpi
     */
    public static function kpi(array $kpi): string
    {
        return LegacyView::render('admin/components/kpi', [
            'label' => $kpi['label'],
            'value' => $kpi['value'],
            'icon' => $kpi['icon'],
            'raw' => $kpi['raw'] ?? 0,
            'format' => $kpi['format'] ?? 'int',
            'currency' => $kpi['currency'] ?? '',
            'hint' => $kpi['hint'] ?? '',
            'modifier' => isset($kpi['tone']) ? 'adm-kpi--'.$kpi['tone'] : '',
            'action' => $kpi['action'] ?? '',
        ]);
    }

    /** Inteiro com separador de milhar do idioma atual (1.234 / 1,234). */
    public static function integer(int $value): string
    {
        return number_format($value, 0, '', str_starts_with(app()->getLocale(), 'en') ? ',' : '.');
    }

    /** Status de rifa (Raffle::STATUS_*) → chave de badge. Encerrada com ganhador = "drawn". */
    public static function raffleStatus(int $status, bool $hasWinners = false): string
    {
        return match ($status) {
            Raffle::STATUS_AWAITING_PAYMENT => 'awaiting',
            Raffle::STATUS_ACTIVE => 'active',
            Raffle::STATUS_FINISHED => $hasWinners ? 'drawn' : 'finished',
            default => 'unknown',
        };
    }

    /** Situação do número na rifa (D/R/I) → chave de badge. */
    public static function numberStatus(string $situation): string
    {
        return match ($situation) {
            Raffle::AVAILABLE => 'available',
            Raffle::RESERVED => 'pending',
            Raffle::UNAVAILABLE => 'paid',
            default => 'unknown',
        };
    }

    /** "Sorteio hoje", "Sorteio em 4 dias", "Sorteio era em 10/11/2026"… */
    public static function drawWhen(?Carbon $date): string
    {
        if (! $date) {
            return __('admin.dashboard.draw.none');
        }

        $days = (int) Carbon::today()->diffInDays($date->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => __('admin.dashboard.draw.past', ['date' => $date->translatedFormat(__('common.date_format'))]),
            $days === 0 => __('admin.dashboard.draw.today'),
            $days === 1 => __('admin.dashboard.draw.tomorrow'),
            default => trans_choice('admin.dashboard.draw.in_days', $days, ['count' => $days]),
        };
    }

    /**
     * Páginas compactas da paginação: [1, null, 4, 5, 6, null, 14] (null = reticências).
     *
     * @return list<int|null>
     */
    public static function pageItems(int $current, int $last, int $around = 1): array
    {
        if ($last <= 1) {
            return [];
        }

        $current = max(1, min($current, $last));
        $pages = array_unique([1, $last, ...range(max(1, $current - $around), min($last, $current + $around))]);
        sort($pages);

        $items = [];
        $previous = 0;
        foreach ($pages as $page) {
            if ($page - $previous === 2) {
                $items[] = $page - 1; // um único número escondido: mostra em vez de "…"
            } elseif ($page - $previous > 2) {
                $items[] = null;
            }
            $items[] = $page;
            $previous = $page;
        }

        return $items;
    }
}
