<?php

namespace App\Domain\Formats;

/**
 * Assigns qualifying best-placed (e.g. third-placed) teams to the knockout
 * slots reserved for them so that no team is drawn against a side from its
 * own group — the job FIFA/UEFA hand-solve with a published allocation
 * table per group combination.
 *
 * Rather than store those lookup tables, this finds a rematch-free
 * assignment directly with a backtracking bipartite match: slots are filled
 * in bracket order, teams tried in ranking order, so the result is
 * deterministic and tends to keep the strongest thirds in the earliest
 * slots. Any group combination that admits a rematch-free layout (all the
 * ones FIFA's tables cover, and more) resolves here too.
 *
 * When no rematch-free assignment exists — a lopsided combination, or a
 * third-vs-third slot whose opponent group can't be known ahead of the
 * allocation — the leftover teams are placed in order and flagged as
 * rematches, so the caller can surface them rather than fail.
 */
class BestPlacedAllocator
{
    /**
     * @param  array<int, array{id: int, name: string, acronym: string, group: string}>  $teams  qualifying thirds, best rank first
     * @param  array<int, array{index: int, opponent_group: string|null}>  $slots  best-placed bracket slots, in bracket order
     * @return array<int, array{team: array{id: int, name: string, acronym: string, group: string}, rematch: bool}> keyed by the slot's entrant index
     */
    public function allocate(array $teams, array $slots): array
    {
        $teams = array_values($teams);
        $slots = array_values($slots);

        $order = $this->solve($teams, $slots, 0, []);

        // No fully rematch-free layout: fall back to rank-order placement and
        // flag every slot whose team ends up facing its own group.
        if ($order === null) {
            $order = array_keys($teams);
        }

        $allocation = [];

        foreach ($slots as $position => $slot) {
            $team = $teams[$order[$position]];
            $allocation[$slot['index']] = [
                'team' => $team,
                'rematch' => $slot['opponent_group'] !== null && $slot['opponent_group'] === $team['group'],
            ];
        }

        return $allocation;
    }

    /**
     * Backtracking search for a rematch-free team ordering: returns an array
     * mapping slot position → team index, or null when none exists.
     *
     * @param  array<int, array{id: int, name: string, acronym: string, group: string}>  $teams
     * @param  array<int, array{index: int, opponent_group: string|null}>  $slots
     * @param  array<int, bool>  $used
     * @return array<int, int>|null
     */
    private function solve(array $teams, array $slots, int $position, array $used): ?array
    {
        if ($position === count($slots)) {
            return [];
        }

        $opponentGroup = $slots[$position]['opponent_group'];

        foreach ($teams as $teamIndex => $team) {
            if (isset($used[$teamIndex])) {
                continue;
            }

            if ($opponentGroup !== null && $team['group'] === $opponentGroup) {
                continue;
            }

            $rest = $this->solve($teams, $slots, $position + 1, $used + [$teamIndex => true]);

            if ($rest !== null) {
                return [$position => $teamIndex] + $rest;
            }
        }

        return null;
    }
}
