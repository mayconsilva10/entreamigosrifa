<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Raffle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Raffle>
 */
class RaffleFactory extends Factory
{
    protected $model = Raffle::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'share_code' => Str::random(10),
            'title' => fake()->sentence(3),
            'number_price' => 10,
            'total_numbers' => 100,
            'numbers' => json_encode(Raffle::generateNumbers(100)),
            'status' => Raffle::STATUS_ACTIVE,
        ];
    }

    public function awaitingPayment(): static
    {
        return $this->state(['status' => Raffle::STATUS_AWAITING_PAYMENT]);
    }

    /** Marca números com a situação informada (R/I) para o cliente informado. */
    public function withNumbers(array $numbers, string $situation, int $customerId): static
    {
        return $this->afterCreating(function (Raffle $raffle) use ($numbers, $situation, $customerId) {
            $list = $raffle->numbersList();
            foreach ($list as $item) {
                if (in_array($item->number, $numbers, true)) {
                    $item->situation = $situation;
                    $item->customer_id = $customerId;
                    $item->pay = $situation === Raffle::UNAVAILABLE ? 'S' : 'N';
                }
            }
            $raffle->setNumbersList($list);
            $raffle->save();
        });
    }
}
