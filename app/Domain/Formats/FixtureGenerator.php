<?php

namespace App\Domain\Formats;

use App\Models\Stage;
use Illuminate\Support\Collection;

/**
 * Produces fixture pairs for a Stage.
 *
 * Implementations are pure functions over the stage's domain state — they do
 * not persist anything or schedule anything. The GenerateFixtures action is
 * responsible for picking the right generator (via FormatRegistry) and
 * persisting the resulting pairs into the games table inside a transaction.
 *
 * Pair shape: each entry is an associative array with home_team_id,
 * away_team_id, and a nullable group_id (set only for grouped formats like
 * GroupStage and Conference).
 */
interface FixtureGenerator
{
    /**
     * @return Collection<int, array{home_team_id: int, away_team_id: int, group_id: int|null}>
     */
    public function generate(Stage $stage): Collection;
}
