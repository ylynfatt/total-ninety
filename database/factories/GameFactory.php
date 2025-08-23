<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locations = [
            'Wembley Stadium', 'Old Trafford', 'Anfield', 'Emirates Stadium', 'Stamford Bridge',
            'Etihad Stadium', 'Tottenham Hotspur Stadium', 'St. James Park', 'London Stadium',
            'King Power Stadium', 'Goodison Park', 'Amex Stadium',
        ];

        return [
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'match_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'location' => $this->faker->randomElement($locations),
        ];
    }
}
