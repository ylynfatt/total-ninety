<?php

namespace App\Domain\Formats;

use App\Models\Stage;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Single-elimination bracket: lose once and you're out.
 *
 * Phase 3c only emits **round-1** fixtures. Later rounds get populated as
 * results come in — the gamecast/results workflow knows how to advance
 * winners into the next slot. This keeps the generator deterministic and
 * the data model simple (no placeholder "winner of game X" rows).
 *
 * Bracket sizing: for n teams, the smallest power of two ≥ n is the
 * bracket capacity. The number of byes is (capacity - n). Round 1 plays
 * (n - capacity/2) games — i.e., enough games to thin the field down to
 * capacity/2 teams for round 2.
 *
 * For powers of 2 (n in {2, 4, 8, 16, …}), there are no byes and round 1
 * has n/2 games.
 *
 * Pairing: lowest seed (first in input) plays highest seed (last in input),
 * second-lowest plays second-highest, and so on. The top (n - capacity/2)
 * seeds receive byes — they sit out round 1 entirely.
 */
class SingleEliminationGenerator implements FixtureGenerator
{
    /**
     * @return Collection<int, array{home_team_id: int, away_team_id: int, group_id: int|null}>
     */
    public function generate(Stage $stage): Collection
    {
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

        // Round-1 game count: thin the field from n teams to capacity/2
        // teams. Each game eliminates one team.
        $round1Games = $n - ($capacity / 2);

        // Teams that play in round 1: the lowest seeds. The top `$byes`
        // seeds skip round 1.
        $playing = $teams->slice($byes)->values();

        $pairs = collect();
        $left = 0;
        $right = $playing->count() - 1;

        while ($left < $right) {
            $pairs->push([
                'home_team_id' => $playing[$left]->id,
                'away_team_id' => $playing[$right]->id,
                'group_id' => null,
            ]);

            $left++;
            $right--;
        }

        // Sanity check — should match $round1Games exactly.
        if ($pairs->count() !== (int) $round1Games) {
            throw new DomainException(
                "SingleElimination math error: produced {$pairs->count()} games but expected {$round1Games}."
            );
        }

        return $pairs;
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
