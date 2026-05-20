<?php

namespace Database\Factories;

use App\Models\Reference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reference>
 */
class ReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'journal',
            'data' => [
                'authors' => 'Smith, J.',
                'year' => '2023',
                'title' => 'An example paper',
                'journal' => 'Journal of Examples',
                'volume' => '10',
                'issue' => '2',
                'pages' => '1-12',
            ],
        ];
    }
}
