<?php

namespace App\Domain\Formats;

use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Produces fixture pairs (home team id, away team id) for a given set of teams.
 *
 * Implementations are pure functions over the team collection — they do not
 * persist anything, schedule anything, or know about the Stage that's
 * generating fixtures. The GenerateFixtures action is responsible for picking
 * the right generator (via FormatRegistry) and persisting the pairs into the
 * games table inside a transaction.
 */
interface FixtureGenerator
{
    /**
     * @param  Collection<int, Team>  $teams
     * @return Collection<int, array{home_team_id: int, away_team_id: int}>
     */
    public function generate(Collection $teams): Collection;
}
