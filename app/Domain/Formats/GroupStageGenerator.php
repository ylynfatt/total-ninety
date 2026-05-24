<?php

namespace App\Domain\Formats;

use App\Models\Stage;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Group-stage format: teams are pre-assigned to groups, and each group plays
 * its own single round-robin. World-Cup-style.
 *
 * Each group of n teams contributes n(n-1)/2 fixtures. Total fixtures =
 * sum over groups. Every emitted pair carries the originating group_id so
 * the standings calculator can group-scope its query later.
 *
 * stage.config can carry the per-group leg count via 'legs_per_group'
 * (default 1). legs_per_group=2 produces double round-robin per group
 * (rare in soccer group stages but supported for symmetry).
 */
class GroupStageGenerator implements FixtureGenerator
{
    /**
     * @return Collection<int, array{home_team_id: int, away_team_id: int, group_id: int|null}>
     */
    public function generate(Stage $stage): Collection
    {
        $groups = $stage->groups;

        if ($groups->isEmpty()) {
            throw new DomainException(
                "Stage [{$stage->id}] is a GroupStage but has no groups defined."
            );
        }

        $legs = (int) ($stage->config['legs_per_group'] ?? 1);
        $legs = max(1, $legs);

        $pairs = collect();

        foreach ($groups as $group) {
            $groupPairs = RoundRobinSingleGenerator::pairsFor($group->teams)
                ->map(fn (array $pair): array => [
                    'home_team_id' => $pair['home_team_id'],
                    'away_team_id' => $pair['away_team_id'],
                    'group_id' => $group->id,
                ]);

            $pairs = $pairs->concat($groupPairs);

            // For multi-leg formats, append reversed pairs once per extra leg.
            for ($leg = 2; $leg <= $legs; $leg++) {
                $pairs = $pairs->concat(
                    $groupPairs->map(fn (array $pair): array => [
                        'home_team_id' => $pair['away_team_id'],
                        'away_team_id' => $pair['home_team_id'],
                        'group_id' => $pair['group_id'],
                    ])
                );
            }
        }

        return $pairs;
    }
}
