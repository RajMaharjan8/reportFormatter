<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'report_id' => Report::factory(),
            'gateway' => 'esewa',
            'mode' => 'test',
            'amount' => 50,
            'transaction_uuid' => (string) Str::uuid(),
            'status' => Payment::STATUS_PENDING,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_COMPLETED,
        ]);
    }

    public function khalti(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => 'khalti',
            'pidx' => Str::random(22),
        ]);
    }
}
