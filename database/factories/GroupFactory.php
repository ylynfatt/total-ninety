<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stage_id' => Stage::factory(),
            // Unique within (stage_id, name) — defer to a counter so multiple
            // groups in the same stage don't collide on the unique index.
            'name' => sprintf('Group %s', strtoupper(fake()->unique()->lexify('???'))),
            'order' => fake()->numberBetween(0, 10),
        ];
    }
}
