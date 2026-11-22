<?php

return [

    // Pasta do tema em resources/templates
    'theme' => env('RIFA_THEME', 'default'),

    // Valor padrão cobrado por número gerado para liberar a rifa (tabela platform_settings, id = 1, tem prioridade)
    'price_per_number' => (float) env('RIFA_PRICE_PER_NUMBER', 0.10),

    // Link de suporte exibido no menu do painel
    'support_url' => env('RIFA_SUPPORT_URL', 'https://wa.me/5518997134115?text=Olá+estou+com+dúvidas+sobre+o+sistema'),

    // Cidade usada no payload PIX
    'pix_city' => env('RIFA_PIX_CITY', 'Tupa'),

    /*
    | Internacionalização
    |
    | locales: idiomas disponíveis (código Laravel => nome exibido no seletor).
    |   Para adicionar um idioma: criar lang/{codigo}/*.php e incluir aqui.
    | currencies: moedas aceitas na criação da rifa (ISO 4217).
    | default_currency: moeda das rifas existentes / sem moeda definida.
    */
    'locales' => [
        'pt_BR' => 'Português (Brasil)',
        'en' => 'English',
    ],

    'currencies' => ['BRL', 'USD', 'EUR', 'GBP'],

    'default_currency' => env('RIFA_DEFAULT_CURRENCY', 'BRL'),

];
