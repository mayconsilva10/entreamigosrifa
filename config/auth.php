<?php

use App\Models\Customer;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'admin'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'customers'),
    ],

    /*
     * Dois "lados" logados de forma independente, ambos sobre a tabela customers:
     *  - admin:   painel do dono da rifa (/admin)
     *  - website: colaborador que compra números em /share/{codigo}
     */
    'guards' => [
        'admin' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
        'website' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
    ],

    'providers' => [
        'customers' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', Customer::class),
        ],
    ],

    'passwords' => [
        'customers' => [
            'provider' => 'customers',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
