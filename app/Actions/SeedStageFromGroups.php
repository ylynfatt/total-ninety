<?php

namespace App\Actions;

use App\Domain\Formats\EntrantSlot;
use App\Domain\Standings\BestPlacedCalculator;
use App\Domain\Standings\StandingsRegistry;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Stage;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fill a knockout stage's round-1 games from the previous grouped stage's
 * final standings, following the stage's entrant slot descriptors — the
 * stage-to-stage analog of AdvanceBracketWinner.
 *
 * preview() resolves every slot to a concrete team (or an explanation of
 * why it can't) without touching the database, so the admin can review the
 * seeding before confirming. execute() runs the same resolution and writes
 * the teams inside a transaction.
 *
 * Idempotent: re-running after a result correction overwrites round-1
 * teams with the (possibly different) qualifiers — but only while no
 * round-1 game has kicked off; after that the bracket is live and refuses
 * to be reshuffled.
 */
class SeedStageFromGroups
{
    public function __construct(
        private readonly StandingsRegistry $standings,
        private readonly BestPlacedCalculator $bestPlaced,
    ) {
        //
    }

    /**
     * Resolve every entrant slot against the source standings.
     *
     * @return array{
     *     source: array{id: int, name: string},
     *     source_complete: bool,
     *     slots: array<int, array{label: string, team: array{id: int, name: string, acronym: string}|null, error: string|null}>,
     * }
     */
    public function preview(Stage $stage): array
    {
        $entrants = EntrantSlot::listForStage($stage);

        if ($entrants === []) {
            throw new DomainException("Stage [{$stage->id}] has no entrant slots configured.");
        }

        $source = $stage->previousGroupedStage();

        if ($source === null) {
            throw new DomainException("Stage [{$stage->id}] has no earlier grouped stage to seed from.");
        }

        $source->load(['groups.teams', 'games.result', 'season.teams']);

        $resolver = $this->makeResolver($source);

        return [
            'source' => ['id' => $source->id, 'name' => $source->name],
            'source_complete' => $source->games->isNotEmpty()
                && $source->games->every(fn (Game $game) => $game->status->isFinal()),
            'slots' => array_map(function (EntrantSlot $slot) use ($resolver): array {
                try {
                    $team = $resolver($slot);

                    return ['label' => $slot->label(), 'team' => $team, 'error' => null];
                } catch (DomainException $e) {
                    return ['label' => $slot->label(), 'team' => null, 'error' => $e->getMessage()];
                }
            }, $entrants),
        ];
    }

    /**
     * Resolve and write the round-1 teams. Throws when any slot can't be
     * resolved or the bracket is no longer safe to overwrite.
     */
    public function execute(Stage $stage): void
    {
        $preview = $this->preview($stage);
        $slots = $preview['slots'];

        foreach ($slots as $index => $slot) {
            if ($slot['error'] !== null) {
                throw new DomainException('Slot '.($index + 1)." ({$slot['label']}): {$slot['error']}");
            }
        }

        $roundOne = $stage->games()
            ->where('round', 1)
            ->orderBy('bracket_position')
            ->get();

        if ($roundOne->count() !== intdiv(count($slots), 2)) {
            throw new DomainException(
                "Stage [{$stage->id}] has {$roundOne->count()} round-1 games but ".count($slots).' entrant slots — generate fixtures before seeding.'
            );
        }

        $started = $roundOne->first(fn (Game $game) => $game->status !== GameStatus::Scheduled);

        if ($started !== null) {
            throw new DomainException(
                "Game [{$started->id}] in round 1 has already started; the bracket can no longer be re-seeded."
            );
        }

        DB::transaction(function () use ($roundOne, $slots): void {
            foreach ($roundOne as $game) {
                $game->update([
                    'home_team_id' => $slots[2 * $game->bracket_position]['team']['id'],
                    'away_team_id' => $slots[2 * $game->bracket_position + 1]['team']['id'],
                ]);
            }
        });
    }

    /**
     * Build a closure resolving one EntrantSlot to a team array, with the
     * per-group standings and the best-placed ranking computed lazily and
     * cached across slots.
     *
     * @return callable(EntrantSlot): array{id: int, name: string, acronym: string}
     */
    private function makeResolver(Stage $source): callable
    {
        $calculator = $this->standings->for($source->format);
        $groupTables = [];
        $bestPlacedRows = null;

        return function (EntrantSlot $slot) use ($source, $calculator, &$groupTables, &$bestPlacedRows): array {
            if ($slot->type === 'best_placed') {
                $bestPlacedRows ??= $this->bestPlaced->calculate($source, ($source->advances_count ?? 2) + 1);

                $row = $bestPlacedRows->get($slot->rank - 1)
                    ?? throw new DomainException("the best-placed ranking only has {$bestPlacedRows->count()} teams.");

                return ['id' => $row->row->team_id, 'name' => $row->row->team_name, 'acronym' => $row->row->team_acronym];
            }

            $group = $source->groups->firstWhere('name', $slot->group)
                ?? throw new DomainException("no group named \"{$slot->group}\" exists in {$source->name}.");

            $groupTables[$group->id] ??= $calculator->calculate($source, $group);

            /** @var Collection $table */
            $table = $groupTables[$group->id];

            $row = $table->get($slot->position - 1)
                ?? throw new DomainException("{$slot->group} has only {$table->count()} teams.");

            return ['id' => $row->team_id, 'name' => $row->team_name, 'acronym' => $row->team_acronym];
        };
    }
}
