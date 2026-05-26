<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
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
            'status' => GameStatus::Scheduled,
            'current_minute' => null,
        ];
    }

    public function live(int $minute = 23): self
    {
        return $this->state(['status' => GameStatus::Live, 'current_minute' => $minute]);
    }

    public function halfTime(): self
    {
        return $this->state(['status' => GameStatus::HalfTime, 'current_minute' => 45]);
    }

    public function fullTime(): self
    {
        return $this->state(['status' => GameStatus::FullTime, 'current_minute' => 90]);
    }
}
