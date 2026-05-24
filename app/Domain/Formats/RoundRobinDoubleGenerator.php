<?php

namespace App\Domain\Formats;

use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Double round-robin: every team plays every other team twice — once at home
 * and once away. Standard Premier League / Bundesliga / La Liga format.
 *
 * Produces n(n-1) fixtures for n teams. Implemented by composing
 * RoundRobinSingleGenerator and concatenating reversed pairs.
 */
class RoundRobinDoubleGenerator implements FixtureGenerator
{
    public function __construct(private readonly RoundRobinSingleGenerator $base)
    {
        //
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return Collection<int, array{home_team_id: int, away_team_id: int}>
     */
    public function generate(Collection $teams): Collection
    {
        $forward = $this->base->generate($teams);

        $reverse = $forward->map(fn (array $pair): array => [
            'home_team_id' => $pair['away_team_id'],
            'away_team_id' => $pair['home_team_id'],
        ]);

        return $forward->concat($reverse);
    }
}
