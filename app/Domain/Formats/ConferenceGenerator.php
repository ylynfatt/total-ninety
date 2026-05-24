<?php

namespace App\Domain\Formats;

use App\Models\Stage;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Conference format: teams are split into conferences (NHL/NBA/MLS-style).
 * Each conference plays an internal round-robin; configurable
 * cross-conference legs add inter-conference matchups.
 *
 * stage.config knobs:
 * - 'intra_conference_legs' (int, default 2): how many times each pair
 *   inside the same conference plays each other. 2 = home + away.
 * - 'cross_conference_legs' (int, default 0): how many times every pair
 *   from different conferences plays each other. 0 = no inter-conference
 *   games at all (e.g., a strict conference-only regular season).
 *
 * Conferences are modeled as Groups on the Stage. group_team pivot
 * assigns teams to a conference. Every emitted pair carries the
 * home team's conference id as group_id so standings can be scoped per
 * conference.
 */
class ConferenceGenerator implements FixtureGenerator
{
    /**
     * @return Collection<int, array{home_team_id: int, away_team_id: int, group_id: int|null}>
     */
    public function generate(Stage $stage): Collection
    {
        $conferences = $stage->groups;

        if ($conferences->count() < 2) {
            throw new DomainException(
                "Stage [{$stage->id}] is a Conference format but has ".
                'fewer than 2 conferences (groups) defined.'
            );
        }

        $config = $stage->config ?? [];
        $intra = max(1, (int) ($config['intra_conference_legs'] ?? 2));
        $cross = max(0, (int) ($config['cross_conference_legs'] ?? 0));

        $pairs = collect();

        // 1. Intra-conference round-robin per conference.
        foreach ($conferences as $conference) {
            $conferencePairs = RoundRobinSingleGenerator::pairsFor($conference->teams)
                ->map(fn (array $pair): array => [
                    'home_team_id' => $pair['home_team_id'],
                    'away_team_id' => $pair['away_team_id'],
                    'group_id' => $conference->id,
                ]);

            for ($leg = 1; $leg <= $intra; $leg++) {
                $pairs = $pairs->concat(
                    $leg === 1
                        ? $conferencePairs
                        : $conferencePairs->map(fn (array $pair): array => [
                            'home_team_id' => $leg % 2 === 0 ? $pair['away_team_id'] : $pair['home_team_id'],
                            'away_team_id' => $leg % 2 === 0 ? $pair['home_team_id'] : $pair['away_team_id'],
                            'group_id' => $pair['group_id'],
                        ])
                );
            }
        }

        // 2. Cross-conference matchups. For every pair of distinct
        //    conferences, every cross-conference team pair plays
        //    $cross times (alternating home/away).
        if ($cross > 0) {
            $confs = $conferences->values();

            for ($a = 0; $a < $confs->count(); $a++) {
                for ($b = $a + 1; $b < $confs->count(); $b++) {
                    foreach ($confs[$a]->teams as $homeTeam) {
                        foreach ($confs[$b]->teams as $awayTeam) {
                            for ($leg = 1; $leg <= $cross; $leg++) {
                                $pairs->push([
                                    'home_team_id' => $leg % 2 === 0 ? $awayTeam->id : $homeTeam->id,
                                    'away_team_id' => $leg % 2 === 0 ? $homeTeam->id : $awayTeam->id,
                                    // Tag with the home team's conference for
                                    // each leg, so cross-conference standings
                                    // can still attribute the game.
                                    'group_id' => $leg % 2 === 0 ? $confs[$b]->id : $confs[$a]->id,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        return $pairs;
    }
}
