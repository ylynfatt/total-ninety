<?php

namespace App\Actions;

use App\Domain\Formats\BestPlacedAllocator;
use App\Domain\Formats\EntrantSlot;
use App\Domain\Standings\BestPlacedCalculator;
use App\Domain\Standings\StandingRow;
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
 * Group slots ("Winner Group A") resolve independently against their group
 * table. The best-placed slots are resolved as a pool: the top-N qualifying
 * thirds are assigned to those slots by BestPlacedAllocator so none is drawn
 * against a side from its own group (see FIFA/UEFA third-placed allocation).
 *
 * preview() resolves every slot to a concrete team (or an explanation of
 * why it can't) without touching the database, so the admin can review the
 * seeding — including any unavoidable rematch — before confirming.
 * execute() runs the same resolution and writes the teams in a transaction.
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
        private readonly BestPlacedAllocator $allocator,
    ) {
        //
    }

    /**
     * Resolve every entrant slot against the source standings.
     *
     * @return array{
     *     source: array{id: int, name: string},
     *     source_complete: bool,
     *     slots: array<int, array{label: string, team: array{id: int, name: string, acronym: string}|null, error: string|null, origin_group: string|null, rematch: bool}>,
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

        $calculator = $this->standings->for($source->format);
        $groupTables = [];

        // First pass: resolve every group slot; note the best-placed slots to
        // resolve together afterwards (their placement depends on each other).
        $slots = [];
        $bestPlacedIndices = [];

        foreach ($entrants as $index => $entrant) {
            if ($entrant->type === 'best_placed') {
                $bestPlacedIndices[] = $index;
                $slots[$index] = null;

                continue;
            }

            $slots[$index] = $this->resolveGroupSlot($source, $calculator, $groupTables, $entrant);
        }

        if ($bestPlacedIndices !== []) {
            $slots = $this->resolveBestPlacedPool($source, $entrants, $slots, $bestPlacedIndices);
        }

        return [
            'source' => ['id' => $source->id, 'name' => $source->name],
            'source_complete' => $source->games->isNotEmpty()
                && $source->games->every(fn (Game $game) => $game->status->isFinal()),
            'slots' => array_values($slots),
        ];
    }

    /**
     * Resolve and write the round-1 teams. Throws when any slot can't be
     * resolved or the bracket is no longer safe to overwrite. An unavoidable
     * rematch is surfaced in the preview but does not block seeding.
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
     * Resolve one "Winner Group A"-style slot against its group table.
     *
     * @param  array<int, Collection<int, StandingRow>>  $groupTables
     * @return array{label: string, team: array{id: int, name: string, acronym: string}|null, error: string|null, origin_group: string|null, rematch: bool}
     */
    private function resolveGroupSlot(Stage $source, $calculator, array &$groupTables, EntrantSlot $slot): array
    {
        $base = ['label' => $slot->label(), 'team' => null, 'error' => null, 'origin_group' => null, 'rematch' => false];

        $group = $source->groups->firstWhere('name', $slot->group);

        if ($group === null) {
            return [...$base, 'error' => "no group named \"{$slot->group}\" exists in {$source->name}."];
        }

        $groupTables[$group->id] ??= $calculator->calculate($source, $group);
        $table = $groupTables[$group->id];

        $row = $table->get($slot->position - 1);

        if ($row === null) {
            return [...$base, 'error' => "{$slot->group} has only {$table->count()} teams."];
        }

        return [...$base, 'team' => ['id' => $row->team_id, 'name' => $row->team_name, 'acronym' => $row->team_acronym]];
    }

    /**
     * Resolve the best-placed slots together: take the top-N qualifying
     * thirds and let the allocator assign them to the N slots avoiding
     * same-group rematches.
     *
     * @param  array<int, EntrantSlot>  $entrants
     * @param  array<int, array<string, mixed>|null>  $slots
     * @param  array<int, int>  $bestPlacedIndices
     * @return array<int, array<string, mixed>>
     */
    private function resolveBestPlacedPool(Stage $source, array $entrants, array $slots, array $bestPlacedIndices): array
    {
        $position = ($source->advances_count ?? 2) + 1;
        $label = 'Best '.$this->ordinal($position).'-placed';
        $need = count($bestPlacedIndices);

        $ranking = $this->bestPlaced->calculate($source, $position);

        if ($ranking->count() < $need) {
            foreach ($bestPlacedIndices as $index) {
                $slots[$index] = [
                    'label' => $label,
                    'team' => null,
                    'error' => "only {$ranking->count()} best-placed teams are available for {$need} slots.",
                    'origin_group' => null,
                    'rematch' => false,
                ];
            }

            return $slots;
        }

        $teams = $ranking->take($need)
            ->map(fn ($row): array => [
                'id' => $row->row->team_id,
                'name' => $row->row->team_name,
                'acronym' => $row->row->team_acronym,
                'group' => $row->group_name,
            ])
            ->all();

        $allocationSlots = array_map(fn (int $index): array => [
            'index' => $index,
            'opponent_group' => $this->opponentGroup($entrants, $index),
        ], $bestPlacedIndices);

        $allocation = $this->allocator->allocate($teams, $allocationSlots);

        foreach ($bestPlacedIndices as $index) {
            $team = $allocation[$index]['team'];
            $slots[$index] = [
                'label' => $label,
                'team' => ['id' => $team['id'], 'name' => $team['name'], 'acronym' => $team['acronym']],
                'error' => null,
                'origin_group' => $team['group'],
                'rematch' => $allocation[$index]['rematch'],
            ];
        }

        return $slots;
    }

    /**
     * The group a best-placed slot's round-1 opponent comes from, for
     * rematch avoidance. Round-1 partners are consecutive entrants, so the
     * partner is the sibling index (i xor 1). Null when that partner is
     * itself a best-placed slot (its group isn't known before allocation).
     *
     * @param  array<int, EntrantSlot>  $entrants
     */
    private function opponentGroup(array $entrants, int $index): ?string
    {
        $opponent = $entrants[$index ^ 1] ?? null;

        return $opponent !== null && $opponent->type === 'group' ? $opponent->group : null;
    }

    private function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }
}
