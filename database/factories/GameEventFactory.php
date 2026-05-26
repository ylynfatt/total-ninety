<?php

namespace Database\Factories;

use App\Enums\GameEventType;
use App\Models\Game;
use App\Models\GameEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameEvent>
 */
class GameEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'minute' => fake()->numberBetween(1, 90),
            'stoppage' => null,
            'type' => GameEventType::Commentary,
            'team_id' => null,
            'player_id' => null,
            'assist_player_id' => null,
            'secondary_player_id' => null,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function goal(): self
    {
        return $this->state(['type' => GameEventType::Goal]);
    }

    public function yellowCard(): self
    {
        return $this->state(['type' => GameEventType::YellowCard]);
    }

    public function redCard(): self
    {
        return $this->state(['type' => GameEventType::RedCard]);
    }

    public function substitution(): self
    {
        return $this->state(['type' => GameEventType::Substitution]);
    }
}
