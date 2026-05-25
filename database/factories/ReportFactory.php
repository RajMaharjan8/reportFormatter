<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
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
            'cover_format' => 'london_met',
            'student_name' => fake()->name(),
            'module_code' => 'CC'.fake()->numberBetween(1000, 9999),
            'module_title' => fake()->sentence(3),
            'title' => fake()->sentence(4),
            'london_id' => (string) fake()->numberBetween(10000000, 99999999),
            'college_id' => 'IC'.fake()->numberBetween(100000, 999999),
        ];
    }
}
