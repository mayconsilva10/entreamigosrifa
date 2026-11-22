<?php

/*
| Public raffle page (/share/{code}) and server messages used by it.
| Used in templates as {{t:raffle.key}} (or {{t!:raffle.key}} when the value contains HTML).
| Values may contain view {{variables}} (resolved after translation).
*/
return [
    // Titles
    'page_payment' => 'Payment',
    'page_draw' => 'Draw',

    // Hero
    'tap_to_zoom' => 'Tap to zoom',
    'per_number' => 'per ticket',
    'draw_label' => 'Draw:',
    'sold_numbers_aria' => 'Tickets sold',
    'sold_summary' => '<strong>{{soldPercent}}% sold</strong> of {{qtdNumbers}} tickets',
    'organized_by' => 'Organized by',
    'talk' => 'Chat',
    'whatsapp_owner_message' => 'Hi, I would like to know more about the raffle {{URL}}/share/{{shared}}',
    'whatsapp_float_message' => 'Hi, my name is {{name_customer}}, I would like to know more about the raffle. %0D%0D{{URL}}/share/{{shared}}',
    'whatsapp_float_aria' => 'Message the organizer on WhatsApp',
    'share' => 'Share raffle',
    'stat_draw' => 'Draw date',
    'stat_numbers' => 'Tickets',
    'stat_available' => '{{availableNumbers}} available',
    'cta_pick' => 'Pick my tickets',
    'share_short' => 'Share',
    'how_it_works' => 'How it works',
    'trust_pix' => 'Pay with Pix',
    'trust_instant' => 'Instant tickets',
    'trust_notified' => 'Winner notified',
    'photo_alt' => ':title — photo :number',

    // Countdown
    'countdown_today' => 'today',
    'countdown_tomorrow' => 'tomorrow',
    'countdown_days' => 'in :count days',

    // Number picker
    'pick_title' => 'Pick your lucky numbers 🍀',
    'available_summary' => '<strong x-text="counts.D">{{availableNumbers}}</strong> of <span x-text="total">{{qtdNumbers}}</span> still available',
    'lookup_link' => 'Look up my tickets by phone',
    'pick_modes_aria' => 'How to pick your numbers',
    'tab_pick' => 'Pick',
    'tab_lucky' => 'Lucky dip',
    'lucky_hint' => 'We pick random available numbers for you',
    'search_number' => 'Find a number',
    'band_previous' => 'Previous range',
    'band_select' => 'Number range',
    'band_next' => 'Next range',
    'take_suggestion' => 'Take',
    'only_free' => 'Available only',
    'show_more' => 'Show more',
    'legend' => 'Legend',
    'legend_reserved' => 'Reserved',
    'legend_paid' => 'Paid',
    'grid_aria' => 'Numbers in this range',

    // Rules
    'rules_title' => 'Description and rules',

    // Cart / checkout
    'cart_aria' => 'Your cart',
    'clear_aria' => 'Clear selected numbers',
    'clear' => 'Clear',
    'total' => 'Total',
    'close' => 'Close',
    'checkout_lead' => 'Just enter your WhatsApp number to reserve your tickets',
    'phone_label' => 'Your WhatsApp number',
    'phone_mask' => '(99) 99999-9999',
    'phone_placeholder' => '(00) 00000-0000',
    'phone_help' => 'We only use it to let you know if you win',
    'name_label' => 'What should we call you?',
    'name_placeholder' => 'Your name',
    'wait' => 'Please wait…',
    'reserving' => 'Reserving your tickets…',
    'back_to_pick' => 'Go back and pick numbers',
    'reserved_numbers' => 'Reserved numbers:',
    'total_colon' => 'Total:',
    'timer' => 'Your numbers are held for <strong x-text="remaining" role="timer"></strong>. Pay now to secure them.',
    'pay_mercado_pago' => 'Pay with Mercado Pago',
    'pay_pix' => 'Pay with Pix',
    'already_paid' => 'I have paid',

    // Pix
    'pix_copy' => 'COPY PIX CODE',
    'pix_copied' => '✓ Pix code copied!',
    'pix_qr_alt' => 'Pix QR code for payment',
    'pix_code_label' => 'Pix copy-and-paste code',
    'pix_missing' => 'The raffle organizer has not set up a Pix key yet.',
    'pix_description' => 'Raffle: :numbers',

    // Winners
    'no_winner' => 'No winner',

    // Preview (Open Graph)
    'og_offer' => ':price per ticket · Draw: :date',
    'og_card_badge' => 'ONLINE RAFFLE',
    'og_card_per_number' => 'per ticket',
    'og_card_draw' => 'Draw',
    'og_card_sold' => ':percent% sold',
    'og_card_numbers' => ':count tickets',
    'og_card_cta' => 'Pick your lucky numbers',

    // Alerts (?status=)
    'status_created' => 'All set! Now pick your lucky numbers 🍀',
    'status_invalid_phone' => 'Check your WhatsApp number: it needs the area code + number.',
    'status_not_registered' => 'Pick your numbers and enter your WhatsApp number to reserve them.',
    'status_not_selected' => 'Pick at least 1 number to pay.',

    // API / quick sign-up
    'invalid_phone' => 'Enter a valid phone number with area code.',
    'ask_name' => 'What should we call you?',
    'name_required' => 'Enter your name.',
    'default_customer_name' => 'Participant',
    'register_to_reserve' => 'Enter your phone number to reserve the tickets.',
    'register_to_pick' => 'Sign up quickly to pick your numbers.',
    'not_found' => 'Raffle not found.',
    'invalid_number' => 'Invalid number.',
    'pick_at_least_one' => 'Pick at least one number.',
    'max_numbers' => 'You can reserve up to :max numbers at a time.',
    'duplicate_numbers' => 'Your selection has duplicate numbers.',
    'number_already_paid' => "Number ':number' has already been processed and cannot be removed. Wait for the draw — good luck!",
    'number_reserved' => "Number ':number' reserved. Thank you for supporting my raffle!",
    'number_removed' => "Number ':number' was removed.",
    'number_taken' => "This number was already taken by another friend!\n Please pick another number.",
];
