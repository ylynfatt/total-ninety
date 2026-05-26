<?php

namespace App\Domain\Standings;

use App\Models\Game;
use App\Models\Group;
use App\Models\Result;
use App\Models\Stage;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Calculates a soccer-style standings table for any "table" format:
 * RoundRobinSingle, RoundRobinDouble, GroupStage (per group), Conference
 * (per conference).
 *
 * Reads completed Games + their Results, folds them into per-team stats
 * (played / W / D / L / GF / GA / points), then applies the tiebreaker
 * order from stage.config['tiebreakers']. Default tiebreakers, in priority
 * order:
 *   1. points    — more points first
 *   2. gd        — better goal difference first
 *   3. gf        — more goals scored first
 *   4. name      — alphabetical (stable tiebreak)
 *
 * Points per outcome are also configurable via stage.config:
 *   - points_per_win  (default 3)
 *   - points_per_draw (default 1)
 *   - points_per_loss (default 0)
 *
 * The `form` string is built from each team's last 5 chronological games
 * (ordered by match_date, then game id as a stable tiebreak). Most recent
 * is on the left. Games without a recorded result are skipped — only
 * decided games count.
 *
 * Head-to-head tiebreaking is intentionally NOT implemented in this PR.
 * It's a richer rule (build a mini-table over only the tied teams' games)
 * and will land separately if the user community needs it.
 */
class RoundRobinStandingsCalculator implements StandingsCalculator
{
    private const DEFAULT_TIEBREAKERS = ['points', 'gd', 'gf', 'name'];

    /**
     * @return Collection<int, StandingRow>
     */
    public function calculate(Stage $stage, ?Group $group = null): Collection
    {
        [$teams, $games] = $this->scope($stage, $group);

        $config = $stage->config ?? [];
        $pointsWin = (int) ($config['points_per_win'] ?? 3);
        $pointsDraw = (int) ($config['points_per_draw'] ?? 1);
        $pointsLoss = (int) ($config['points_per_loss'] ?? 0);

        $tiebreakers = $config['tiebreakers'] ?? self::DEFAULT_TIEBREAKERS;
        if (! is_array($tiebreakers) || $tiebreakers === []) {
            $tiebreakers = self::DEFAULT_TIEBREAKERS;
        }

        // Build a blank ledger for every team, including teams that haven't
        // played yet — they appear with zeros across the board.
        $ledger = $teams->keyBy('id')->map(fn (Team $team) => $this->blankLedger($team))->all();

        // Per-team chronologically-ordered list of W/D/L characters for form.
        $playedSequence = $teams->keyBy('id')->map(fn () => [])->all();

        // Only fully-decided games contribute to the table.
        $decided = $games->filter(fn (Game $g) => $g->result instanceof Result);

        // Sort once by match_date (nulls last) then by id so the form string
        // is deterministic when dates are missing.
        $decided = $decided->sortBy([
            ['match_date', 'asc'],
            ['id', 'asc'],
        ])->values();

        foreach ($decided as $game) {
            $home = $game->home_team_id;
            $away = $game->away_team_id;
            $hs = (int) $game->result->home_team_score;
            $as = (int) $game->result->away_team_score;

            if (! isset($ledger[$home], $ledger[$away])) {
                // A game between teams that are no longer in the group/season
                // roster — skip rather than throw, so the calculator stays
                // resilient to mid-tournament roster changes.
                continue;
            }

            $ledger[$home]['played']++;
            $ledger[$away]['played']++;
            $ledger[$home]['goals_for'] += $hs;
            $ledger[$home]['goals_against'] += $as;
            $ledger[$away]['goals_for'] += $as;
            $ledger[$away]['goals_against'] += $hs;

            if ($hs > $as) {
                $ledger[$home]['won']++;
                $ledger[$away]['lost']++;
                $ledger[$home]['points'] += $pointsWin;
                $ledger[$away]['points'] += $pointsLoss;
                $playedSequence[$home][] = 'W';
                $playedSequence[$away][] = 'L';
            } elseif ($hs < $as) {
                $ledger[$away]['won']++;
                $ledger[$home]['lost']++;
                $ledger[$away]['points'] += $pointsWin;
                $ledger[$home]['points'] += $pointsLoss;
                $playedSequence[$home][] = 'L';
                $playedSequence[$away][] = 'W';
            } else {
                $ledger[$home]['drawn']++;
                $ledger[$away]['drawn']++;
                $ledger[$home]['points'] += $pointsDraw;
                $ledger[$away]['points'] += $pointsDraw;
                $playedSequence[$home][] = 'D';
                $playedSequence[$away][] = 'D';
            }
        }

        $rows = collect();
        foreach ($ledger as $teamId => $stats) {
            $team = $teams->firstWhere('id', $teamId);
            $sequence = $playedSequence[$teamId] ?? [];
            $form = implode('', array_reverse(array_slice($sequence, -5)));

            $rows->push(new StandingRow(
                team_id: $teamId,
                team_name: (string) ($team->name ?? ''),
                team_acronym: (string) ($team->acronym ?? ''),
                played: $stats['played'],
                won: $stats['won'],
                drawn: $stats['drawn'],
                lost: $stats['lost'],
                goals_for: $stats['goals_for'],
                goals_against: $stats['goals_against'],
                points: $stats['points'],
                form: $form,
            ));
        }

        return $rows->sort(fn (StandingRow $a, StandingRow $b) => $this->compareRows($a, $b, $tiebreakers))
            ->values();
    }

    /**
     * @return array{0: Collection<int, Team>, 1: Collection<int, Game>}
     */
    private function scope(Stage $stage, ?Group $group): array
    {
        if ($group !== null) {
            $teams = $group->teams;
            $games = $stage->games->filter(fn (Game $g) => $g->group_id === $group->id);

            return [$teams, $games];
        }

        return [$stage->season->teams, $stage->games];
    }

    /**
     * @return array<string, int>
     */
    private function blankLedger(Team $team): array
    {
        return [
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0,
        ];
    }

    /**
     * @param  array<int, string>  $tiebreakers
     */
    private function compareRows(StandingRow $a, StandingRow $b, array $tiebreakers): int
    {
        foreach ($tiebreakers as $rule) {
            $cmp = match ($rule) {
                'points' => $b->points <=> $a->points,
                'gd' => $b->goal_difference <=> $a->goal_difference,
                'gf' => $b->goals_for <=> $a->goals_for,
                'name' => strcasecmp($a->team_name, $b->team_name),
                default => 0,
            };

            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    }
}
