<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-1 year', '+6 months');
        $year = (int) $startsOn->format('Y');

        return [
            'league_id' => League::factory(),
            // Append a unique suffix so multiple factory-built seasons in the
            // same league don't collide on the (league_id, name) unique index.
            'name' => sprintf('%d/%02d #%d', $year, ($year + 1) % 100, fake()->unique()->numberBetween(1, 99999)),
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => fake()->dateTimeBetween($startsOn, '+1 year')->format('Y-m-d'),
            'is_active' => false,
        ];
    }

    public function active(): self
    {
        return $this->state(['is_active' => true]);
    }
}
