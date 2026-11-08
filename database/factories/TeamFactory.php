<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
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
            'user_id' => User::factory(),
            'name' => fake()->unique()->word().' FC',
            'acronym' => strtoupper(fake()->unique()->lexify('???')),
            'year_founded' => fake()->numberBetween(1850, 1950),
            'home_ground' => fake()->word().' Stadium',
        ];
    }
}
