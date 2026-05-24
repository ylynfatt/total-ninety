<?php

namespace App\Domain\Formats;

use App\Models\Stage;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Single round-robin: every team plays every other team exactly once.
 *
 * Produces n(n-1)/2 fixtures for n teams in the parent season. Home/away
 * assignment is deterministic — the team appearing earlier in the collection
 * is assigned as the home team for every pair it participates in.
 *
 * Scheduling (assigning dates and matchweeks) is a separate concern handled
 * outside the generator.
 */
class RoundRobinSingleGenerator implements FixtureGenerator
{
    /**
     * @return Collection<int, array{home_team_id: int, away_team_id: int, group_id: int|null}>
     */
    public function generate(Stage $stage): Collection
    {
        return self::pairsFor($stage->season->teams);
    }

    /**
     * Pure pair-maker reused by grouped formats (GroupStage, Conference)
     * to run round-robin per group. Returns pairs with `group_id => null`;
     * callers attach the right group_id themselves.
     *
     * @param  Collection<int, Team>  $teams
     * @return Collection<int, array{home_team_id: int, away_team_id: int, group_id: int|null}>
     */
    public static function pairsFor(Collection $teams): Collection
    {
        $list = $teams->values();
        $pairs = collect();

        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                $pairs->push([
                    'home_team_id' => $list[$i]->id,
                    'away_team_id' => $list[$j]->id,
                    'group_id' => null,
                ]);
            }
        }

        return $pairs;
    }
}
