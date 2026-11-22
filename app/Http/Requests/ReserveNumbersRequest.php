<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Reserva em lote dos números escolhidos no carrinho da página /share/{codigo}.
 */
class ReserveNumbersRequest extends FormRequest
{
    /** Limite de números por requisição (evita payloads abusivos). */
    public const MAX_NUMBERS = 500;

    public function authorize(): bool
    {
        return auth('website')->check();
    }

    /** Visitante sem cadastro: o front abre o drawer de identificação. */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => __('raffle.register_to_reserve'),
            'needs_register' => true,
        ], 401));
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'numbers' => ['required', 'array', 'min:1', 'max:'.self::MAX_NUMBERS],
            'numbers.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'numbers.required' => __('raffle.pick_at_least_one'),
            'numbers.min' => __('raffle.pick_at_least_one'),
            'numbers.max' => __('raffle.max_numbers', ['max' => self::MAX_NUMBERS]),
            'numbers.*.integer' => __('raffle.invalid_number'),
            'numbers.*.min' => __('raffle.invalid_number'),
            'numbers.*.distinct' => __('raffle.duplicate_numbers'),
        ];
    }

    /** @return array<int, int> */
    public function numbers(): array
    {
        return array_map('intval', (array) $this->validated('numbers'));
    }
}
