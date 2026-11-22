<?php

namespace App\Support;

use Mpdf\QrCode\Output\Svg;
use Mpdf\QrCode\QrCode;

/**
 * Gera o "copia e cola" PIX estático (padrão BR Code do Banco Central) e o QR Code.
 * O QR Code é gerado em SVG para não depender da extensão GD.
 */
class Pix
{
    private const ID_PAYLOAD_FORMAT_INDICATOR = '00';

    private const ID_POINT_OF_INITIATION_METHOD = '01';

    private const ID_MERCHANT_ACCOUNT_INFORMATION = '26';

    private const ID_MERCHANT_ACCOUNT_INFORMATION_GUI = '00';

    private const ID_MERCHANT_ACCOUNT_INFORMATION_KEY = '01';

    private const ID_MERCHANT_ACCOUNT_INFORMATION_DESCRIPTION = '02';

    private const ID_MERCHANT_CATEGORY_CODE = '52';

    private const ID_TRANSACTION_CURRENCY = '53';

    private const ID_TRANSACTION_AMOUNT = '54';

    private const ID_COUNTRY_CODE = '58';

    private const ID_MERCHANT_NAME = '59';

    private const ID_MERCHANT_CITY = '60';

    private const ID_ADDITIONAL_DATA_FIELD_TEMPLATE = '62';

    private const ID_ADDITIONAL_DATA_FIELD_TEMPLATE_TXID = '05';

    private const ID_CRC16 = '63';

    public function __construct(
        private string $pixKey,
        private string $merchantName,
        private string $merchantCity,
        private string $description,
        private float $amount,
        private string $txid = 'MyDev',
        private bool $uniquePayment = false,
    ) {}

    public function payload(): string
    {
        $payload = $this->value(self::ID_PAYLOAD_FORMAT_INDICATOR, '01')
            .($this->uniquePayment ? $this->value(self::ID_POINT_OF_INITIATION_METHOD, '12') : '')
            .$this->merchantAccountInformation()
            .$this->value(self::ID_MERCHANT_CATEGORY_CODE, '0000')
            .$this->value(self::ID_TRANSACTION_CURRENCY, '986')
            .$this->value(self::ID_TRANSACTION_AMOUNT, number_format($this->amount, 2, '.', ''))
            .$this->value(self::ID_COUNTRY_CODE, 'BR')
            .$this->value(self::ID_MERCHANT_NAME, mb_substr($this->merchantName ?: 'N', 0, 25))
            .$this->value(self::ID_MERCHANT_CITY, mb_substr($this->merchantCity ?: 'N', 0, 15))
            .$this->value(self::ID_ADDITIONAL_DATA_FIELD_TEMPLATE, $this->value(self::ID_ADDITIONAL_DATA_FIELD_TEMPLATE_TXID, $this->txid));

        return $payload.$this->crc16($payload);
    }

    /** QR Code em SVG, codificado em base64 (para usar em data:image/svg+xml;base64,...). */
    public function qrCodeBase64(int $size = 400): string
    {
        return base64_encode((new Svg)->output(new QrCode($this->payload()), $size));
    }

    /** Renderiza o bloco de pagamento PIX (template website/payments/banco_central/pix). */
    public function render(): string
    {
        return LegacyView::render('website/payments/banco_central/pix', [
            'qrCode' => $this->qrCodeBase64(),
            'codeQrCode' => $this->payload(),
            'pixKey' => $this->pixKey,
        ]);
    }

    private function value(string $id, string $value): string
    {
        return $id.str_pad((string) mb_strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    private function merchantAccountInformation(): string
    {
        $gui = $this->value(self::ID_MERCHANT_ACCOUNT_INFORMATION_GUI, 'br.gov.bcb.pix');
        $key = strlen($this->pixKey) ? $this->value(self::ID_MERCHANT_ACCOUNT_INFORMATION_KEY, $this->pixKey) : '';
        $description = strlen($this->description) ? $this->value(self::ID_MERCHANT_ACCOUNT_INFORMATION_DESCRIPTION, $this->description) : '';

        return $this->value(self::ID_MERCHANT_ACCOUNT_INFORMATION, $gui.$key.$description);
    }

    private function crc16(string $payload): string
    {
        $payload .= self::ID_CRC16.'04';
        $result = 0xFFFF;

        for ($offset = 0, $length = strlen($payload); $offset < $length; $offset++) {
            $result ^= (ord($payload[$offset]) << 8);
            for ($bit = 0; $bit < 8; $bit++) {
                if (($result <<= 1) & 0x10000) {
                    $result ^= 0x1021;
                }
                $result &= 0xFFFF;
            }
        }

        return self::ID_CRC16.'04'.str_pad(strtoupper(dechex($result)), 4, '0', STR_PAD_LEFT);
    }
}
