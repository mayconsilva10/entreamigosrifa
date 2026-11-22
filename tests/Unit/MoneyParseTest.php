<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyParseTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?float}> */
    public static function values(): array
    {
        return [
            'pt-BR' => ['R$ 1.234,56', 1234.56],
            'en' => ['$1,234.56', 1234.56],
            'máscara antiga' => ['R$ 1,234.56', 1234.56],
            'só centavos pt' => ['0,10', 0.1],
            'decimal com 1 casa' => ['10,5', 10.5],
            'ponto decimal' => ['10.50', 10.5],
            'milhar pt' => ['1.000', 1000.0],
            'milhões en' => ['1,000,000', 1000000.0],
            'euro' => ['1.234,56 €', 1234.56],
            'inteiro' => ['25', 25.0],
            'vazio' => ['', null],
            'nulo' => [null, null],
            'texto' => ['abc', null],
        ];
    }

    #[DataProvider('values')]
    public function test_parse(?string $value, ?float $expected): void
    {
        $this->assertSame($expected, Money::parse($value));
    }

    public function test_separators_follow_locale(): void
    {
        $this->assertSame([',', '.'], Money::separators('pt_BR'));
        $this->assertSame(['.', ','], Money::separators('en'));
    }
}
