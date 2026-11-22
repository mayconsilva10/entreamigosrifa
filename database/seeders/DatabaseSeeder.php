<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\PlatformSetting;
use App\Models\Raffle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados para rodar localmente:
     *  - platform_settings (id = 1): valor cobrado por número na liberação da rifa
     *  - conta demo do painel: demo@entreamigos.local / 12345678
     *  - uma rifa demo já liberada: /share/rifa-demo
     */
    public function run(): void
    {
        PlatformSetting::query()->updateOrCreate(['id' => 1], ['price_per_number' => config('rifa.price_per_number')]);

        $demo = Customer::query()->updateOrCreate(
            ['email' => 'demo@entreamigos.local'],
            [
                'name' => 'Dono Demo',
                'phone' => '18999999999',
                'password' => Hash::make('12345678'),
                'settings' => json_encode([
                    'payments' => ['pix' => ['type_key' => 'email', 'pix_key' => 'demo@entreamigos.local'], 'mercado_pago' => ['public_key' => '', 'access_token' => '']],
                    'social' => ['instagram' => '', 'facebook' => '', 'youtube' => '', 'whats_group' => '', 'whats_support' => ''],
                    'notification' => ['telegram' => ['chat_id' => '']],
                ]),
            ]
        );

        Raffle::query()->firstOrCreate(
            ['share_code' => 'rifa-demo'],
            [
                'customer_id' => $demo->id,
                'title' => 'Rifa Demo',
                'description' => '<p>Rifa de demonstração para testes locais.</p>',
                'draw_rules' => '<p>Sorteio pela Loteria Federal.</p>',
                'number_price' => 5,
                'total_numbers' => 100,
                'numbers' => json_encode(Raffle::generateNumbers(100)),
                'photos' => json_encode([]),
                'status' => Raffle::STATUS_ACTIVE,
            ]
        );
    }
}
