<?php

namespace App\Domain\Standings;

use App\Models\Group;
use App\Models\Stage;
use Illuminate\Support\Collection;

/**
 * Produces a standings table for a stage (or one group within a stage).
 *
 * Implementations are pure functions over the stage's persisted games +
 * results. They never write to the DB; they just shape the data.
 *
 * Calling convention:
 *   - ungrouped formats (RoundRobinSingle / RoundRobinDouble): pass the
 *     stage, leave $group null. Teams are drawn from stage.season.teams.
 *   - grouped formats (GroupStage / Conference): pass the stage AND the
 *     specific group whose table you want. Teams are drawn from the
 *     group's roster.
 *
 * Bracket formats (SingleElimination / DoubleElimination) do not produce
 * standings tables — a separate "bracket calculator" handles those — so
 * the StandingsRegistry won't resolve a calculator for them.
 */
interface StandingsCalculator
{
    /**
     * @return Collection<int, StandingRow>
     */
    public function calculate(Stage $stage, ?Group $group = null): Collection;
}
