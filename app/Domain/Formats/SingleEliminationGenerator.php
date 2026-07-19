<?php

namespace App\Domain\Formats;

use App\Models\Stage;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Single-elimination bracket: lose once and you're out.
 *
 * Emits the **entire** bracket up front so the structure is renderable and
 * navigable from the moment fixtures are generated:
 *
 *   - Round 1 holds only the contested games (both teams known). Teams that
 *     draw a bye skip round 1 and are seeded directly into their round-2 slot.
 *   - Rounds 2…final are created as placeholder games whose teams are TBD
 *     (null), to be filled by winners as results come in. Round-2 slots fed by
 *     a bye are pre-populated.
 *
 * Coordinates: each game carries `round` (1 = opening round, highest = final)
 * and `bracket_position` (0-based slot within the round). The winner of round
 * r positions 2p and 2p+1 advances to round r+1 position p — the slot parity
 * (even → home, odd → away) tells advancement which side to fill.
 *
 * Bracket sizing: capacity = the smallest power of two ≥ n. byes = capacity − n
 * (always < capacity/2, so no round-1 game has two byes). Total games across
 * all rounds equals n − 1 — exactly the number needed to crown one winner.
 *
 * Seeding: standard bracket seeding so the top two seeds can only meet in the
 * final. Lowest seed (first in input) is strongest; phantom seeds beyond n are
 * the byes and fall to the strongest real seeds.
 *
 * Entrant mode: when the stage declares config['entrants'] (slot descriptors
 * like "Winner Group A" — see EntrantSlot), the bracket's shape comes from
 * that list instead of the season's teams: every game is emitted as a TBD
 * placeholder, and the seeding action fills round 1 from the previous stage's
 * final standings. Consecutive descriptor pairs are the round-1 matchups, so
 * the descriptor list *is* the pairing template.
 */
class SingleEliminationGenerator implements FixtureGenerator
{
    /**
     * @return Collection<int, array{home_team_id: int|null, away_team_id: int|null, group_id: null, round: int, bracket_position: int}>
     */
    public function generate(Stage $stage): Collection
    {
        $entrants = EntrantSlot::listForStage($stage);

        if ($entrants !== []) {
            return $this->generatePlaceholders($stage, count($entrants));
        }

        $teams = $stage->season->teams->values();
        $n = $teams->count();

        if ($n < 2) {
            return collect();
        }

        $capacity = self::nextPowerOfTwo($n);
        $byes = $capacity - $n;

        if ($byes >= $n) {
            throw new DomainException(
                "Stage [{$stage->id}] has too few teams for single-elimination: ".
                "{$n} teams would produce {$byes} byes in a {$capacity}-team bracket."
            );
        }

        $rounds = (int) log($capacity, 2);
        $seedOrder = self::seedOrder($rounds);

        $teamForSeed = fn (int $seed): ?int => $seed <= $n ? $teams[$seed - 1]->id : null;

        $games = collect();

        // Bye teams are seeded directly into their round-2 slot:
        // [round2Position][slot 0=home|1=away] => team_id.
        $round2Fill = [];

        $round1Slots = intdiv($capacity, 2);

        for ($position = 0; $position < $round1Slots; $position++) {
            $homeId = $teamForSeed($seedOrder[2 * $position]);
            $awayId = $teamForSeed($seedOrder[2 * $position + 1]);

            if ($homeId !== null && $awayId !== null) {
                $games->push([
                    'home_team_id' => $homeId,
                    'away_team_id' => $awayId,
                    'group_id' => null,
                    'round' => 1,
                    'bracket_position' => $position,
                ]);

                continue;
            }

            // Exactly one side is a bye — the real team advances to round 2.
            $round2Fill[intdiv($position, 2)][$position % 2] = $homeId ?? $awayId;
        }

        for ($round = 2; $round <= $rounds; $round++) {
            $slots = intdiv($capacity, 2 ** $round);

            for ($position = 0; $position < $slots; $position++) {
                $games->push([
                    'home_team_id' => $round === 2 ? ($round2Fill[$position][0] ?? null) : null,
                    'away_team_id' => $round === 2 ? ($round2Fill[$position][1] ?? null) : null,
                    'group_id' => null,
                    'round' => $round,
                    'bracket_position' => $position,
                ]);
            }
        }

        return $games;
    }

    /**
     * Emit an all-TBD bracket sized to the entrant list: every game in every
     * round carries null teams. Round 1 gets filled by the stage-seeding
     * action; later rounds fill as winners advance.
     *
     * @return Collection<int, array{home_team_id: null, away_team_id: null, group_id: null, round: int, bracket_position: int}>
     */
    private function generatePlaceholders(Stage $stage, int $capacity): Collection
    {
        if ($capacity < 2 || $capacity !== self::nextPowerOfTwo($capacity)) {
            throw new DomainException(
                "Stage [{$stage->id}] has {$capacity} entrant slots; a knockout bracket needs a power of two (2, 4, 8, 16, …)."
            );
        }

        $rounds = (int) log($capacity, 2);
        $games = collect();

        for ($round = 1; $round <= $rounds; $round++) {
            $slots = intdiv($capacity, 2 ** $round);

            for ($position = 0; $position < $slots; $position++) {
                $games->push([
                    'home_team_id' => null,
                    'away_team_id' => null,
                    'group_id' => null,
                    'round' => $round,
                    'bracket_position' => $position,
                ]);
            }
        }

        return $games;
    }

    /**
     * Standard bracket seed ordering for a 2^rounds-team bracket. Returns
     * 1-based seeds laid out so consecutive pairs (0,1), (2,3)… are the
     * round-1 matchups, with seed 1 and seed 2 in opposite halves.
     *
     * @return array<int, int>
     */
    private static function seedOrder(int $rounds): array
    {
        $seeds = [1, 2];

        for ($round = 2; $round <= $rounds; $round++) {
            $size = 2 ** $round;
            $next = [];

            foreach ($seeds as $seed) {
                $next[] = $seed;
                $next[] = $size + 1 - $seed;
            }

            $seeds = $next;
        }

        return $seeds;
    }

    /**
     * Smallest power of two ≥ $n. nextPowerOfTwo(5) === 8, nextPowerOfTwo(8) === 8.
     */
    private static function nextPowerOfTwo(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }

        return 2 ** (int) ceil(log($n, 2));
    }
}
