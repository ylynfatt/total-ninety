<?php

namespace Database\Factories;

use App\Enums\StageFormat;
use App\Models\Season;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stage>
 */
class StageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            // Unique within (season_id, name) — defer to a counter to avoid
            // collisions when multiple stages are created in the same season.
            'name' => sprintf('Stage %d', fake()->unique()->numberBetween(1, 99999)),
            'order' => fake()->numberBetween(0, 10),
            'format' => StageFormat::RoundRobinSingle,
            'starts_on' => null,
            'ends_on' => null,
            'advances_count' => null,
            'config' => null,
        ];
    }

    public function roundRobinDouble(): self
    {
        return $this->state(['format' => StageFormat::RoundRobinDouble]);
    }

    public function groupStage(): self
    {
        return $this->state(['format' => StageFormat::GroupStage]);
    }

    public function singleElimination(): self
    {
        return $this->state(['format' => StageFormat::SingleElimination]);
    }

    public function conference(): self
    {
        return $this->state(['format' => StageFormat::Conference]);
    }
}
