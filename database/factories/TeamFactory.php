<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' FC',
            'acronym' => strtoupper(fake()->unique()->lexify('???')),
            'year_founded' => fake()->numberBetween(1850, 1950),
            'home_ground' => fake()->word().' Stadium',
        ];
    }
}
