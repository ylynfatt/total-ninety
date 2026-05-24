<?php

namespace App\Domain\Formats;

use App\Models\Stage;
use Illuminate\Support\Collection;

/**
 * Double round-robin: every team plays every other team twice — once at home
 * and once away. Standard Premier League / Bundesliga / La Liga format.
 *
 * Produces n(n-1) fixtures for n teams in the parent season. Implemented by
 * composing RoundRobinSingleGenerator::pairsFor() and concatenating reversed
 * pairs.
 */
class RoundRobinDoubleGenerator implements FixtureGenerator
{
    /**
     * @return Collection<int, array{home_team_id: int, away_team_id: int, group_id: int|null}>
     */
    public function generate(Stage $stage): Collection
    {
        $forward = RoundRobinSingleGenerator::pairsFor($stage->season->teams);

        $reverse = $forward->map(fn (array $pair): array => [
            'home_team_id' => $pair['away_team_id'],
            'away_team_id' => $pair['home_team_id'],
            'group_id' => null,
        ]);

        return $forward->concat($reverse);
    }
}
