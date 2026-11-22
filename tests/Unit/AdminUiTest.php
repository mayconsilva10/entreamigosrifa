<?php

namespace Tests\Unit;

use App\Models\Raffle;
use App\Support\AdminUi;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminUiTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function badges(): array
    {
        return [
            'paid' => ['paid', 'paid', 'bi-check-circle-fill', 'Pago'],
            'pending' => ['pending', 'pending', 'bi-hourglass-split', 'Pendente'],
            'expired' => ['expired', 'expired', 'bi-clock-history', 'Expirado'],
            'winner' => ['winner', 'winner', 'bi-trophy-fill', 'Ganhador'],
            'available' => ['available', 'available', 'bi-circle', 'Disponível'],
            'active' => ['active', 'paid', 'bi-broadcast', 'Ativa'],
            'awaiting' => ['awaiting', 'pending', 'bi-hourglass-split', 'Aguardando liberação'],
            'finished' => ['finished', 'expired', 'bi-flag-fill', 'Encerrada'],
            'drawn' => ['drawn', 'winner', 'bi-trophy-fill', 'Sorteada'],
        ];
    }

    #[DataProvider('badges')]
    public function test_status_maps_to_tone_icon_and_label(string $status, string $tone, string $icon, string $label): void
    {
        $this->assertSame(
            ['status' => $status, 'tone' => $tone, 'icon' => $icon, 'label' => $label],
            AdminUi::badgeData($status)
        );
    }

    public function test_unknown_status_falls_back_to_neutral(): void
    {
        $this->assertSame(
            ['status' => 'unknown', 'tone' => 'neutral', 'icon' => 'bi-question-circle', 'label' => 'Desconhecido'],
            AdminUi::badgeData('whatever')
        );
    }

    public function test_badge_html_has_icon_text_and_escaped_custom_label(): void
    {
        $html = AdminUi::badge('paid', '<b>Pago</b>');

        $this->assertStringContainsString('adm-badge--paid', $html);
        $this->assertStringContainsString('bi-check-circle-fill', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('&lt;b&gt;Pago&lt;/b&gt;', $html);
    }

    public function test_badge_label_follows_locale(): void
    {
        app()->setLocale('en');

        $this->assertSame('Awaiting release', AdminUi::badgeData('awaiting')['label']);
    }

    public function test_raffle_status_mapping(): void
    {
        $this->assertSame('awaiting', AdminUi::raffleStatus(Raffle::STATUS_AWAITING_PAYMENT));
        $this->assertSame('active', AdminUi::raffleStatus(Raffle::STATUS_ACTIVE));
        $this->assertSame('finished', AdminUi::raffleStatus(Raffle::STATUS_FINISHED));
        $this->assertSame('drawn', AdminUi::raffleStatus(Raffle::STATUS_FINISHED, hasWinners: true));
        $this->assertSame('unknown', AdminUi::raffleStatus(99));
    }

    public function test_number_situation_mapping(): void
    {
        $this->assertSame('available', AdminUi::numberStatus(Raffle::AVAILABLE));
        $this->assertSame('pending', AdminUi::numberStatus(Raffle::RESERVED));
        $this->assertSame('paid', AdminUi::numberStatus(Raffle::UNAVAILABLE));
        $this->assertSame('unknown', AdminUi::numberStatus('X'));
    }

    public function test_compact_page_items(): void
    {
        $this->assertSame([], AdminUi::pageItems(1, 1));
        $this->assertSame([1, 2, 3], AdminUi::pageItems(2, 3));
        $this->assertSame([1, null, 4, 5, 6, null, 14], AdminUi::pageItems(5, 14));
        $this->assertSame([1, 2, 3, 4, null, 14], AdminUi::pageItems(3, 14));
        $this->assertSame([1, 2, null, 14], AdminUi::pageItems(1, 14));
        $this->assertSame([1, null, 13, 14], AdminUi::pageItems(99, 14));
    }
}
