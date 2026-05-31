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
 *   2. h2h       — head-to-head record among the tied teams (see below)
 *   3. gd        — better goal difference first
 *   4. gf        — more goals scored first
 *   5. name      — alphabetical (stable tiebreak)
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
 * Head-to-head: when teams are level on the preceding criteria, a mini-table
 * is built from ONLY the games among exactly those tied teams, ranked by
 * mini-table points, then mini goal difference, then mini goals for. A 3+ way
 * tie that only partially resolves lets the still-level teams fall through to
 * the remaining tiebreakers — so ranking is a recursive, grouped sort rather
 * than a pairwise comparison (which can't resolve non-transitive cycles).
 */
class RoundRobinStandingsCalculator implements StandingsCalculator
{
    private const DEFAULT_TIEBREAKERS = ['points', 'h2h', 'gd', 'gf', 'name'];

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

        return $this->rankRows($rows, $tiebreakers, $decided, [
            'win' => $pointsWin,
            'draw' => $pointsDraw,
            'loss' => $pointsLoss,
        ]);
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
     * Rank rows by applying the tiebreakers in order. At each level the rows
     * are sorted by that criterion, then any still-tied subgroup is ranked
     * recursively by the remaining criteria. This is what lets head-to-head
     * operate on exactly the set of currently-tied teams and lets a partially
     * resolved 3+ way tie fall through to the next rule.
     *
     * @param  Collection<int, StandingRow>  $rows
     * @param  array<int, string>  $tiebreakers
     * @param  Collection<int, Game>  $decided
     * @param  array{win: int, draw: int, loss: int}  $points
     * @return Collection<int, StandingRow>
     */
    private function rankRows(Collection $rows, array $tiebreakers, Collection $decided, array $points): Collection
    {
        if ($rows->count() <= 1 || $tiebreakers === []) {
            return $rows->values();
        }

        $rule = $tiebreakers[0];
        $rest = array_slice($tiebreakers, 1);

        // A key extractor and a comparator for the current rule. Comparators
        // return negative when $a should rank ahead of $b.
        if ($rule === 'h2h') {
            $mini = $this->headToHead($rows->pluck('team_id')->all(), $decided, $points);
            $keyOf = fn (StandingRow $row): array => [
                $mini[$row->team_id]['points'],
                $mini[$row->team_id]['goals_for'] - $mini[$row->team_id]['goals_against'],
                $mini[$row->team_id]['goals_for'],
            ];
            $compare = fn (array $a, array $b): int => ($b[0] <=> $a[0]) ?: ($b[1] <=> $a[1]) ?: ($b[2] <=> $a[2]);
        } elseif ($rule === 'name') {
            $keyOf = fn (StandingRow $row): string => $row->team_name;
            $compare = fn (string $a, string $b): int => strcasecmp($a, $b);
        } else {
            $keyOf = fn (StandingRow $row): int => match ($rule) {
                'points' => $row->points,
                'gd' => $row->goal_difference,
                'gf' => $row->goals_for,
                default => 0,
            };
            $compare = fn (int $a, int $b): int => $b <=> $a;
        }

        $sorted = $rows
            ->sort(fn (StandingRow $a, StandingRow $b): int => $compare($keyOf($a), $keyOf($b)))
            ->values();

        // Walk the sorted rows, gathering runs of equal-key rows and ranking
        // each run by the remaining tiebreakers.
        $ranked = collect();
        $bucket = collect();
        $bucketKey = null;

        foreach ($sorted as $row) {
            $key = $keyOf($row);

            if ($bucket->isEmpty() || $compare($key, $bucketKey) === 0) {
                $bucket->push($row);
                $bucketKey = $key;

                continue;
            }

            $ranked = $ranked->concat($this->rankRows($bucket, $rest, $decided, $points));
            $bucket = collect([$row]);
            $bucketKey = $key;
        }

        if ($bucket->isNotEmpty()) {
            $ranked = $ranked->concat($this->rankRows($bucket, $rest, $decided, $points));
        }

        return $ranked->values();
    }

    /**
     * Build a head-to-head mini-table over only the games played among the
     * given teams. Returns per-team points / goals_for / goals_against.
     *
     * @param  array<int, int>  $teamIds
     * @param  Collection<int, Game>  $decided
     * @param  array{win: int, draw: int, loss: int}  $points
     * @return array<int, array{points: int, goals_for: int, goals_against: int}>
     */
    private function headToHead(array $teamIds, Collection $decided, array $points): array
    {
        $inGroup = array_flip($teamIds);

        $mini = [];
        foreach ($teamIds as $id) {
            $mini[$id] = ['points' => 0, 'goals_for' => 0, 'goals_against' => 0];
        }

        foreach ($decided as $game) {
            $home = $game->home_team_id;
            $away = $game->away_team_id;

            if (! isset($inGroup[$home], $inGroup[$away])) {
                continue;
            }

            $hs = (int) $game->result->home_team_score;
            $as = (int) $game->result->away_team_score;

            $mini[$home]['goals_for'] += $hs;
            $mini[$home]['goals_against'] += $as;
            $mini[$away]['goals_for'] += $as;
            $mini[$away]['goals_against'] += $hs;

            if ($hs > $as) {
                $mini[$home]['points'] += $points['win'];
                $mini[$away]['points'] += $points['loss'];
            } elseif ($hs < $as) {
                $mini[$away]['points'] += $points['win'];
                $mini[$home]['points'] += $points['loss'];
            } else {
                $mini[$home]['points'] += $points['draw'];
                $mini[$away]['points'] += $points['draw'];
            }
        }

        return $mini;
    }
}
