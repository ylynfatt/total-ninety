<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'first_name' => fake()->firstName('male'),
            'last_name' => fake()->lastName(),
            'shirt_number' => fake()->numberBetween(1, 99),
            'position' => fake()->randomElement(['GK', 'DF', 'MF', 'FW']),
            'date_of_birth' => fake()->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
        ];
    }

    public function freeAgent(): self
    {
        return $this->state(['team_id' => null]);
    }
}
