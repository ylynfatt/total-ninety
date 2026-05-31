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
 * Pair shape: each entry is an associative array with home_team_id and
 * away_team_id (both nullable — knockout placeholder slots are TBD until a
 * winner advances), a nullable group_id (set only for grouped formats like
 * GroupStage and Conference), and optional bracket coordinates round and
 * bracket_position (set only by knockout formats).
 */
interface FixtureGenerator
{
    /**
     * @return Collection<int, array{home_team_id: int|null, away_team_id: int|null, group_id?: int|null, round?: int|null, bracket_position?: int|null}>
     */
    public function generate(Stage $stage): Collection;
}
