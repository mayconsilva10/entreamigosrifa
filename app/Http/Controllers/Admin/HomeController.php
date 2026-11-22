<?php

namespace App\Http\Controllers\Admin;

use App\Models\Raffle;
use App\Services\AdminDashboard;
use App\Services\LoteriaFederal;
use App\Support\AdminUi;
use App\Support\LegacyView;
use App\Support\Media;
use App\Support\Money;

/**
 * Dashboard do painel: KPIs, fila "Precisa da sua atenção", progresso das rifas e Loteria Federal.
 */
class HomeController extends PanelController
{
    public function index(AdminDashboard $dashboard, LoteriaFederal $loteria): string
    {
        $customer = $this->customer();
        $summary = $dashboard->summary($customer);
        $firstTime = $summary['total'] === 0;

        $content = LegacyView::render('admin/modules/home/index', [
            'header' => LegacyView::render('admin/components/page_header', [
                'breadcrumb' => '',
                'title' => e(__('admin.title.dashboard')),
                'subtitle' => '<p class="adm-page-header__subtitle">'
                    .e(__('admin.dashboard.subtitle', ['name' => strtok(trim((string) $customer->name), ' ') ?: ''])).'</p>',
                'actions' => '<a class="adm-btn adm-btn--primary" href="'.e(url('/admin/sortition/create')).'">'
                    .'<i class="bi bi-plus-lg" aria-hidden="true"></i>'.e(__('admin.nav.new_raffle')).'</a>',
            ]),
            'kpis' => $firstTime ? '' : $this->kpis($summary),
            'main' => $firstTime ? $this->onboarding($summary) : $this->attention($summary).$this->progress($summary),
            'lottery' => $this->lottery($loteria->lastResult()),
        ]);

        return $this->panel(__('admin.title.dashboard'), $content, 'dashboard');
    }

    /** Valores por moeda: "R$ 10,00 · US$ 5.00". Sem valores = zero na moeda padrão. */
    private function money(array $amounts): string
    {
        if ($amounts === []) {
            return Money::format(0);
        }

        return implode(' · ', array_map(fn ($currency, $value) => Money::format($value, $currency), array_keys($amounts), $amounts));
    }

    /** raw/format/currency do countUp: só anima quando há uma única moeda. */
    private function moneyKpi(array $amounts): array
    {
        return count($amounts) === 1
            ? ['raw' => round((float) reset($amounts), 2), 'format' => 'money', 'currency' => (string) key($amounts)]
            : ['raw' => 0, 'format' => 'money', 'currency' => ''];
    }

    private function kpis(array $summary): string
    {
        $pending = $summary['pending'];

        $kpis = AdminUi::kpi([
            'label' => e(__('admin.dashboard.kpi.collected')),
            'value' => e($this->money($summary['collected'])),
            'icon' => 'bi-cash-stack',
            'hint' => e(trans_choice('admin.dashboard.kpi.collected_hint', $summary['paid_numbers'], ['count' => AdminUi::integer($summary['paid_numbers'])])),
        ] + $this->moneyKpi($summary['collected']));

        $kpis .= AdminUi::kpi([
            'label' => e(__('admin.dashboard.kpi.pending')),
            'value' => AdminUi::integer($pending['count']),
            'raw' => $pending['count'],
            'icon' => 'bi-hourglass-split',
            'tone' => $pending['count'] > 0 ? 'pending' : null,
            'hint' => $pending['count'] > 0
                ? e(__('admin.dashboard.kpi.pending_hint', ['amount' => $this->money($pending['amounts'])]))
                : e(__('admin.dashboard.kpi.pending_none')),
            'action' => $pending['raffle_id']
                ? '<a class="adm-kpi__action" href="'.e(url('/admin/sortition/'.$pending['raffle_id'].'/list')).'">'
                    .e(__('admin.dashboard.kpi.pending_cta')).'<i class="bi bi-arrow-right" aria-hidden="true"></i></a>'
                : '',
        ]);

        $kpis .= AdminUi::kpi([
            'label' => e(__('admin.dashboard.kpi.active')),
            'value' => AdminUi::integer($summary['active']),
            'raw' => $summary['active'],
            'icon' => 'bi-broadcast',
            'hint' => $summary['awaiting'] > 0
                ? e(trans_choice('admin.dashboard.kpi.active_awaiting', $summary['awaiting'], ['count' => $summary['awaiting']]))
                : e(trans_choice('admin.dashboard.kpi.active_total', $summary['total'], ['count' => $summary['total']])),
        ]);

        $kpis .= AdminUi::kpi([
            'label' => e(__('admin.dashboard.kpi.sold')),
            'value' => $summary['sold_percent'].'%',
            'raw' => $summary['sold_percent'],
            'format' => 'percent',
            'icon' => 'bi-pie-chart',
            'hint' => e(__('admin.dashboard.kpi.sold_hint', [
                'sold' => AdminUi::integer($summary['sold']),
                'capacity' => AdminUi::integer($summary['capacity']),
            ])),
        ]);

        return '<section class="adm-kpis" aria-label="'.e(__('admin.dashboard.kpi.region')).'">'.$kpis.'</section>';
    }

    private function attention(array $summary): string
    {
        $items = '';
        foreach ($summary['attention'] as $item) {
            $items .= LegacyView::render('admin/modules/home/dashboard/attention_item', $this->attentionVars($item));
        }

        $count = count($summary['attention']);

        return LegacyView::render('admin/modules/home/dashboard/attention', [
            'count' => $count > 0 ? '<span class="adm-count" aria-label="'.e(trans_choice('admin.dashboard.attention.count', $count, ['count' => $count])).'">'.$count.'</span>' : '',
            'items' => $count > 0
                ? '<ul class="adm-attention">'.$items.'</ul>'
                : '<p class="adm-attention__clear"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>'
                    .'<span><strong>'.e(__('admin.dashboard.attention.empty_title')).'</strong> '.e(__('admin.dashboard.attention.empty_text')).'</span></p>',
        ]);
    }

    /** @param  array{type: string, raffle: Raffle|null, count?: int, amount?: float, currency?: string, days?: int}  $item */
    private function attentionVars(array $item): array
    {
        $raffle = $item['raffle'];
        $title = $raffle ? e($raffle->title) : e(__('admin.dashboard.attention.pix_title'));

        [$tone, $icon, $text, $cta, $link] = match ($item['type']) {
            'pending' => [
                'pending', 'bi-hourglass-split',
                trans_choice('admin.dashboard.attention.pending', $item['count'], ['count' => $item['count'], 'amount' => Money::format($item['amount'], $item['currency'])]),
                __('admin.dashboard.attention.pending_cta'), '/admin/sortition/'.$raffle->id.'/list',
            ],
            'awaiting' => [
                'pending', 'bi-credit-card',
                __('admin.dashboard.attention.awaiting'),
                __('admin.dashboard.attention.awaiting_cta'), '/admin/'.$raffle->id.'/checkout',
            ],
            'draw_soon' => [
                'info', 'bi-calendar-event',
                AdminUi::drawWhen($raffle->draw_date),
                __('admin.dashboard.attention.draw_cta'), '/admin/sortition/'.$raffle->id.'/listSortition',
            ],
            'overdue' => [
                'pending', 'bi-calendar-x',
                __('admin.dashboard.attention.overdue', ['date' => $raffle->draw_date->translatedFormat(__('common.date_format'))]),
                __('admin.dashboard.attention.overdue_cta'), '/admin/sortition/'.$raffle->id.'/winnerLottery',
            ],
            default => [
                'pending', 'bi-qr-code',
                __('admin.dashboard.attention.pix'),
                __('admin.dashboard.attention.pix_cta'), '/admin/customer/payment',
            ],
        };

        return [
            'tone' => $tone,
            'icon' => $icon,
            'title' => $title,
            'text' => e($text),
            'cta' => e($cta),
            'link' => e(url($link)),
        ];
    }

    private function progress(array $summary): string
    {
        $cards = '';
        foreach ($summary['raffles'] as $stats) {
            $cards .= $this->raffleCard($stats);
        }

        return LegacyView::render('admin/modules/home/dashboard/progress', [
            'cards' => $cards !== ''
                ? '<ul class="adm-raffle-list">'.$cards.'</ul>'
                : '<p class="adm-muted-note">'.e(__('admin.dashboard.progress.empty')).'</p>',
            'all_link' => e(url('/admin/sortition')),
        ]);
    }

    /** @param  array{raffle: Raffle, currency: string, paid: int, reserved: int, free: int, total: int, percent: int, paid_amount: float, reserved_amount: float}  $stats */
    private function raffleCard(array $stats): string
    {
        $raffle = $stats['raffle'];
        $total = max(1, $stats['total']);
        $photo = $raffle->photosList()[0] ?? null;
        $awaiting = $raffle->status === Raffle::STATUS_AWAITING_PAYMENT;

        $secondary = $awaiting
            ? '<a class="adm-btn adm-btn--ghost adm-btn--sm" href="'.e(url('/admin/'.$raffle->id.'/checkout')).'">'
                .'<i class="bi bi-credit-card" aria-hidden="true"></i>'.e(__('admin.dashboard.attention.awaiting_cta')).'</a>'
            : '<button type="button" class="adm-btn adm-btn--ghost adm-btn--sm" x-copy="$el.dataset.url" data-url="'.e(url('/share/'.$raffle->share_code)).'">'
                .'<i class="bi bi-link-45deg" aria-hidden="true"></i>'.e(__('admin.dashboard.progress.copy')).'</button>';

        return LegacyView::render('admin/modules/home/dashboard/raffle_card', [
            'photo' => e(Media::exists($photo) ? Media::url($photo) : url('storage/images/system/default-placeholder.png')),
            'title' => e($raffle->title),
            'manage' => e(url('/admin/sortition/'.$raffle->id.'/listSortition')),
            'badge' => AdminUi::badge(AdminUi::raffleStatus($raffle->status, $raffle->winnersList() !== [])),
            'paid_pct' => round($stats['paid'] * 100 / $total, 2),
            'reserved_pct' => round($stats['reserved'] * 100 / $total, 2),
            'bar_label' => e(__('admin.dashboard.progress.bar_label', [
                'paid' => AdminUi::integer($stats['paid']),
                'reserved' => AdminUi::integer($stats['reserved']),
                'free' => AdminUi::integer($stats['free']),
            ])),
            'sold' => e(__('admin.dashboard.progress.sold', [
                'sold' => AdminUi::integer($stats['paid']),
                'total' => AdminUi::integer($stats['total']),
            ])),
            'percent' => $stats['percent'],
            'amount' => e(Money::format($stats['paid_amount'], $stats['currency'])),
            'draw' => e(AdminUi::drawWhen($raffle->draw_date)),
            'secondary' => $secondary,
        ]);
    }

    /** @param  array{tr: string, numeroConcurso: string, numeroConcursoProximo: string, dataApuracao: string}  $result */
    private function lottery(array $result): string
    {
        return LegacyView::render('admin/modules/home/dashboard/lottery', [
            'contest' => e($result['numeroConcurso']),
            'date' => e($result['dataApuracao']),
            'next' => e($result['numeroConcursoProximo']),
            'rows' => $result['tr'] !== ''
                ? '<table class="adm-table adm-table--compact adm-lottery__table"><thead><tr><th scope="col">'.e(__('admin.dashboard.lottery.prize')).'</th>'
                    .'<th scope="col" class="is-num">'.e(__('admin.dashboard.lottery.ticket')).'</th></tr></thead><tbody>'.$result['tr'].'</tbody></table>'
                : '<p class="adm-muted-note">'.e(__('admin.dashboard.lottery.unavailable')).'</p>',
        ]);
    }

    /** Checklist de primeira vez: Pix → criar rifa → compartilhar. */
    private function onboarding(array $summary): string
    {
        $steps = [
            ['done' => $summary['has_pix'], 'key' => 'pix', 'link' => '/admin/customer/payment'],
            ['done' => false, 'key' => 'create', 'link' => '/admin/sortition/create'],
            ['done' => false, 'key' => 'share', 'link' => null],
        ];

        $html = '';
        $nextMarked = false;
        foreach ($steps as $index => $step) {
            $isNext = ! $step['done'] && ! $nextMarked && $step['link'] !== null;
            $nextMarked = $nextMarked || $isNext;

            $action = match (true) {
                $step['done'] => AdminUi::badge('paid', __('admin.dashboard.onboarding.done')),
                $step['link'] === null => '',
                default => '<a class="adm-btn '.($isNext ? 'adm-btn--primary' : 'adm-btn--secondary').' adm-btn--sm" href="'.e(url($step['link'])).'">'
                    .e(__('admin.dashboard.onboarding.'.$step['key'].'_cta')).'</a>',
            };

            $html .= LegacyView::render('admin/modules/home/dashboard/onboarding_step', [
                'state' => $step['done'] ? 'is-done' : ($isNext ? 'is-next' : ''),
                'marker' => $step['done'] ? '<i class="bi bi-check-lg"></i>' : (string) ($index + 1),
                'title' => e(__('admin.dashboard.onboarding.'.$step['key'].'_title')),
                'text' => e(__('admin.dashboard.onboarding.'.$step['key'].'_text')),
                'action' => $action,
            ]);
        }

        $done = count(array_filter(array_column($steps, 'done')));

        return LegacyView::render('admin/modules/home/dashboard/onboarding', [
            'steps' => $html,
            'done' => $done,
            'total' => count($steps),
            'percent' => (int) round($done * 100 / count($steps)),
            'progress' => e(__('admin.dashboard.onboarding.progress', ['done' => $done, 'total' => count($steps)])),
        ]);
    }
}
