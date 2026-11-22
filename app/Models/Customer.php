<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['email', 'name', 'photo', 'phone', 'locale', 'password', 'settings'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Configurações normalizadas (pagamentos, redes sociais, notificações).
     */
    public function settingsArray(): array
    {
        $raw = json_decode((string) $this->settings, true) ?: [];

        return [
            'payments' => [
                'mercado_pago' => [
                    'public_key' => (string) data_get($raw, 'payments.mercado_pago.public_key', ''),
                    'access_token' => (string) data_get($raw, 'payments.mercado_pago.access_token', ''),
                ],
                'pix' => [
                    'type_key' => (string) data_get($raw, 'payments.pix.type_key', ''),
                    'pix_key' => (string) data_get($raw, 'payments.pix.pix_key', ''),
                ],
                'bank' => [
                    'bank' => (string) data_get($raw, 'payments.bank.bank', ''),
                    'type_account' => (string) data_get($raw, 'payments.bank.type_account', ''),
                    'holder' => (string) data_get($raw, 'payments.bank.holder', ''),
                    'document' => (string) data_get($raw, 'payments.bank.document', ''),
                    'agency' => (string) data_get($raw, 'payments.bank.agency', ''),
                    'account' => (string) data_get($raw, 'payments.bank.account', ''),
                ],
            ],
            'social' => [
                'instagram' => (string) data_get($raw, 'social.instagram', ''),
                'facebook' => (string) data_get($raw, 'social.facebook', ''),
                'youtube' => (string) data_get($raw, 'social.youtube', ''),
                'whats_group' => (string) data_get($raw, 'social.whats_group', ''),
                'whats_support' => (string) data_get($raw, 'social.whats_support', ''),
            ],
            'notification' => [
                'telegram' => [
                    'chat_id' => (string) data_get($raw, 'notification.telegram.chat_id', ''),
                ],
            ],
        ];
    }

    public function saveSettings(array $settings): void
    {
        $this->settings = json_encode($settings);
        $this->save();
    }

    /** Primeiro + segundo nome, como o painel exibia. */
    public function shortName(): string
    {
        $parts = explode(' ', trim((string) $this->name));

        return trim($parts[0].' '.($parts[1] ?? ''));
    }

    public function photoUrl(): string
    {
        return Media::url($this->photo, 'storage/images/profile/profile_default.png');
    }

    public function formattedPhone(): string
    {
        return self::formatPhone($this->phone);
    }

    /** 18999999999 -> (18) 99999-9999 */
    public static function formatPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        return '('.substr($phone, 0, 2).') '.substr($phone, 2, 5).'-'.substr($phone, 7, 5);
    }

    public static function onlyDigitsPhone(?string $phone): string
    {
        return str_replace(['(', ')', '-', ' '], '', trim((string) $phone));
    }

    public function raffles(): HasMany
    {
        return $this->hasMany(Raffle::class, 'customer_id');
    }
}
