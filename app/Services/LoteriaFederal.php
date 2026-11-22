<?php

namespace App\Services;

use App\Support\LegacyView;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Último resultado da Loteria Federal (API pública da Caixa), exibido no dashboard e na tela de ganhadores.
 */
class LoteriaFederal
{
    private const URL = 'https://servicebus2.caixa.gov.br/portaldeloterias/api/federal';

    /**
     * Resultado estruturado: concurso, data, próximo concurso e bilhetes premiados por posição (1º ao 5º).
     *
     * @return array{contest: string, date: string, next: string, prizes: list<array{position: int, ticket: string, amount: float}>}
     */
    public function summary(): array
    {
        $response = Cache::remember('loteria-federal', now()->addMinutes(30), function () {
            try {
                return Http::withoutVerifying()->timeout(8)->get(self::URL)->json() ?: [];
            } catch (Throwable) {
                return [];
            }
        });

        $winners = $response['listaMunicipioUFGanhadores'] ?? [];
        $numbers = $response['listaDezenas'] ?? [];
        $prizes = $response['listaRateioPremio'] ?? [];

        usort($winners, fn ($a, $b) => ($a['posicao'] ?? 0) <=> ($b['posicao'] ?? 0));

        $list = [];
        foreach ($winners as $key => $winner) {
            $ticket = preg_replace('/\D/', '', (string) ($numbers[$key] ?? ''));
            if ($ticket === '') {
                continue;
            }
            $list[] = [
                'position' => (int) ($winner['posicao'] ?? $key + 1),
                'ticket' => $ticket,
                'amount' => (float) ($prizes[$key]['valorPremio'] ?? 0),
            ];
        }

        return [
            'contest' => (string) ($response['numero'] ?? '-'),
            'date' => (string) ($response['dataApuracao'] ?? '-'),
            'next' => (string) ($response['numeroConcursoProximo'] ?? '-'),
            'prizes' => $list,
        ];
    }

    /**
     * Formato usado pelo dashboard (linhas da tabela já renderizadas).
     *
     * @return array{tr: string, numeroConcurso: string, numeroConcursoProximo: string, dataApuracao: string}
     */
    public function lastResult(): array
    {
        $summary = $this->summary();

        $rows = '';
        foreach ($summary['prizes'] as $prize) {
            $rows .= LegacyView::render('admin/modules/home/table_loteria_federal', [
                'destino' => $prize['position'],
                'bilhete' => e($prize['ticket']),
            ]);
        }

        return [
            'tr' => $rows,
            'numeroConcurso' => $summary['contest'],
            'numeroConcursoProximo' => $summary['next'],
            'dataApuracao' => $summary['date'],
        ];
    }

    /**
     * Número da rifa sugerido por um bilhete: os últimos dígitos (tantos quanto o maior número da rifa).
     * Ex.: rifa de 100 números usa os 2 últimos dígitos ("…00" = 100). Fora da faixa da rifa → null.
     */
    public static function suggestedNumber(string $ticket, int $totalNumbers): ?int
    {
        $ticket = preg_replace('/\D/', '', $ticket);
        if ($ticket === '' || $totalNumbers < 1) {
            return null;
        }

        // Rifa "redonda" (10, 100, 1.000…): usa um dígito a menos e o zero vale o último número (00 = 100).
        $digits = strlen((string) $totalNumbers);
        $round = $digits > 1 && $totalNumbers === 10 ** ($digits - 1);
        $number = (int) substr($ticket, -($round ? $digits - 1 : $digits));
        if ($number === 0 && $round) {
            $number = $totalNumbers;
        }

        return $number >= 1 && $number <= $totalNumbers ? $number : null;
    }
}
