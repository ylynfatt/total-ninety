<?php

namespace App\Actions;

use App\Domain\Formats\FormatRegistry;
use App\Models\Game;
use App\Models\Stage;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generate the full set of fixtures for a Stage.
 *
 * The action is the bridge between the pure-math FixtureGenerator
 * strategies and persistence. It pulls the teams that play in the
 * stage, asks the right generator (via FormatRegistry) for the pair
 * list, and writes the resulting games to the database inside a
 * transaction so partial failures don't leave half a fixture list
 * behind.
 *
 * Today this only handles ungrouped formats (round-robin single and
 * double) — teams come from the parent season. Grouped formats
 * (GroupStage, Conference) land in Phase 3c.
 *
 * Idempotency: if the stage already has games, the action throws
 * DomainException. The caller is responsible for clearing existing
 * fixtures before regenerating — silent double-up would be worse.
 */
class GenerateFixtures
{
    public function __construct(private readonly FormatRegistry $registry)
    {
        //
    }

    /**
     * @return Collection<int, Game>
     */
    public function execute(Stage $stage): Collection
    {
        if ($stage->games()->exists()) {
            throw new DomainException(
                "Stage [{$stage->id}] already has fixtures; delete them before regenerating."
            );
        }

        $generator = $this->registry->for($stage->format);
        $pairs = $generator->generate($stage);

        if ($pairs->isEmpty()) {
            throw new DomainException(
                "Stage [{$stage->id}] has no teams to generate fixtures from."
            );
        }

        return DB::transaction(fn () => $pairs->map(fn (array $pair) => Game::create([
            'season_id' => $stage->season_id,
            'stage_id' => $stage->id,
            'group_id' => $pair['group_id'] ?? null,
            'home_team_id' => $pair['home_team_id'] ?? null,
            'away_team_id' => $pair['away_team_id'] ?? null,
            'round' => $pair['round'] ?? null,
            'bracket_position' => $pair['bracket_position'] ?? null,
        ])));
    }
}
