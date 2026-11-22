<?php

/*
| Página pública da rifa (/share/{codigo}) e mensagens do servidor usadas nela.
| Chaves usadas em templates como {{t:raffle.chave}} (ou {{t!:raffle.chave}} quando contêm HTML).
| Valores podem conter {{variavel}} da view (resolvidas depois da tradução).
*/
return [
    // Títulos
    'page_payment' => 'Pagamento',
    'page_draw' => 'Sorteio',

    // Hero
    'tap_to_zoom' => 'Toque para ampliar',
    'per_number' => 'por número',
    'draw_label' => 'Sorteio:',
    'sold_numbers_aria' => 'Números vendidos',
    'sold_summary' => '<strong>{{soldPercent}}% vendido</strong> de {{qtdNumbers}} números',
    'organized_by' => 'Organizado por',
    'talk' => 'Falar',
    'whatsapp_owner_message' => 'Olá, gostaria de saber mais sobre a rifa {{URL}}/share/{{shared}}',
    'whatsapp_float_message' => 'Olá me chamo {{name_customer}}, gostaria de saber mais sobre a rifa. %0D%0D{{URL}}/share/{{shared}}',
    'whatsapp_float_aria' => 'Falar com o organizador no WhatsApp',
    'share' => 'Compartilhar rifa',
    'stat_draw' => 'Sorteio',
    'stat_numbers' => 'Números',
    'stat_available' => '{{availableNumbers}} disponíveis',
    'cta_pick' => 'Escolher meus números',
    'share_short' => 'Compartilhar',
    'how_it_works' => 'Como funciona',
    'trust_pix' => 'Pague com Pix',
    'trust_instant' => 'Números na hora',
    'trust_notified' => 'Ganhador avisado',
    'photo_alt' => ':title — foto :number',

    // Contagem regressiva
    'countdown_today' => 'hoje',
    'countdown_tomorrow' => 'amanhã',
    'countdown_days' => 'em :count dias',

    // Seletor de números
    'pick_title' => 'Escolha seus números da sorte 🍀',
    'available_summary' => '<strong x-text="counts.D">{{availableNumbers}}</strong> de <span x-text="total">{{qtdNumbers}}</span> ainda disponíveis',
    'lookup_link' => 'Consultar meus números pelo telefone',
    'pick_modes_aria' => 'Como escolher seus números',
    'tab_pick' => 'Escolher',
    'tab_lucky' => 'Surpresinha',
    'lucky_hint' => 'A gente escolhe números livres ao acaso pra você',
    'search_number' => 'Buscar número',
    'band_previous' => 'Faixa anterior',
    'band_select' => 'Faixa de números',
    'band_next' => 'Próxima faixa',
    'take_suggestion' => 'Quero o',
    'only_free' => 'Só disponíveis',
    'show_more' => 'Mostrar mais',
    'legend' => 'Legenda',
    'legend_reserved' => 'Reservado',
    'legend_paid' => 'Pago',
    'grid_aria' => 'Números da faixa',

    // Regulamento
    'rules_title' => 'Descrição e regulamento',

    // Carrinho / checkout
    'cart_aria' => 'Seu carrinho',
    'clear_aria' => 'Limpar números escolhidos',
    'clear' => 'Limpar',
    'total' => 'Total',
    'close' => 'Fechar',
    'checkout_lead' => 'Só falta seu WhatsApp para reservar seus números',
    'phone_label' => 'Seu WhatsApp',
    'phone_mask' => '(99) 99999-9999',
    'phone_placeholder' => '(00) 00000-0000',
    'phone_help' => 'Usamos só para avisar se você ganhar',
    'name_label' => 'Como podemos te chamar?',
    'name_placeholder' => 'Seu nome',
    'wait' => 'Aguarde…',
    'reserving' => 'Reservando seus números…',
    'back_to_pick' => 'Voltar e escolher números',
    'reserved_numbers' => 'Números reservados:',
    'total_colon' => 'Total:',
    'timer' => 'Seus números estão guardados por <strong x-text="remaining" role="timer"></strong>. Pague para garantir.',
    'pay_mercado_pago' => 'Pagar com Mercado Pago',
    'pay_pix' => 'Pague com Pix',
    'already_paid' => 'Já paguei',

    // Pix
    'pix_copy' => 'COPIAR CÓDIGO PIX',
    'pix_copied' => '✓ Código Pix copiado!',
    'pix_qr_alt' => 'QR Code Pix para pagamento',
    'pix_code_label' => 'Código Pix copia e cola',
    'pix_missing' => 'O dono da rifa ainda não cadastrou uma chave PIX.',
    'pix_description' => 'Rifa: :numbers',

    // Ganhadores
    'no_winner' => 'Não possui Ganhador',

    // Prévia (Open Graph)
    'og_offer' => ':price por número · Sorteio: :date',
    'og_card_badge' => 'RIFA ONLINE',
    'og_card_per_number' => 'por número',
    'og_card_draw' => 'Sorteio',
    'og_card_sold' => ':percent% vendido',
    'og_card_numbers' => ':count números',
    'og_card_cta' => 'Escolha seus números da sorte',

    // Alertas (?status=)
    'status_created' => 'Pronto! Agora escolha seus números da sorte 🍀',
    'status_invalid_phone' => 'Confira seu WhatsApp: precisa ter DDD + número.',
    'status_not_registered' => 'Escolha seus números e informe seu WhatsApp para reservar.',
    'status_not_selected' => 'Escolha ao menos 1 número para pagar.',

    // API / cadastro rápido
    'invalid_phone' => 'Informe um telefone válido com DDD.',
    'ask_name' => 'Como podemos te chamar?',
    'name_required' => 'Informe seu nome.',
    'default_customer_name' => 'Colaborador',
    'register_to_reserve' => 'Informe seu telefone para reservar os números.',
    'register_to_pick' => 'Faça o cadastro rápido para escolher seus números.',
    'not_found' => 'Rifa não encontrada.',
    'invalid_number' => 'Número inválido.',
    'pick_at_least_one' => 'Escolha ao menos um número.',
    'max_numbers' => 'Você pode reservar no máximo :max números por vez.',
    'duplicate_numbers' => 'Há números repetidos na seleção.',
    'number_already_paid' => "O número ':number' já foi processado, não é possível realizar a exclusão, aguarde o sorteio e boa sorte!",
    'number_reserved' => "O número ':number' reservado, muito obrigado por comprar a minha rifa!",
    'number_removed' => "O número ':number' foi removido com sucesso!",
    'number_taken' => "O número já foi marcado por outro amigo!\n Por gentileza escolha outro número.",
];
