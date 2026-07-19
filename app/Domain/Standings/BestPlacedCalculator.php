<?php

namespace App\Domain\Standings;

use App\Models\Group;
use App\Models\Stage;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Ranks the teams finishing in the same position across every group of a
 * grouped stage — the "ranking of third-placed teams" table used by World
 * Cup / Euro style tournaments where the best N of them advance alongside
 * the automatic qualifiers.
 *
 * Each group's table is computed by the stage's regular standings
 * calculator (so per-group tiebreakers, including head-to-head, still
 * apply within the group). The cross-group comparison then uses only
 * points → goal difference → goals for → name: head-to-head is meaningless
 * across groups because the teams never met, which matches how FIFA and
 * UEFA rank third-placed teams.
 *
 * Groups too small to have a team at the requested position are skipped
 * rather than erroring, so a lopsided draw (one group of 3, others of 4)
 * still produces a ranking of the teams that exist.
 */
class BestPlacedCalculator
{
    public function __construct(private readonly StandingsRegistry $registry)
    {
        //
    }

    /**
     * @return Collection<int, BestPlacedRow>
     */
    public function calculate(Stage $stage, int $position): Collection
    {
        if (! $stage->format->hasGroups()) {
            throw new DomainException(
                "Stage [{$stage->id}] has format [{$stage->format->value}], which has no groups to rank best-placed teams across."
            );
        }

        if ($position < 1) {
            throw new DomainException("Best-placed position must be at least 1, got [{$position}].");
        }

        $calculator = $this->registry->for($stage->format);

        return $stage->groups
            ->map(function (Group $group) use ($stage, $calculator, $position): ?BestPlacedRow {
                $row = $calculator->calculate($stage, $group)->get($position - 1);

                return $row === null ? null : new BestPlacedRow($group->id, $group->name, $row);
            })
            ->filter()
            ->sort(fn (BestPlacedRow $a, BestPlacedRow $b): int => ($b->row->points <=> $a->row->points)
                ?: ($b->row->goal_difference <=> $a->row->goal_difference)
                ?: ($b->row->goals_for <=> $a->row->goals_for)
                ?: strcasecmp($a->row->team_name, $b->row->team_name))
            ->values();
    }
}
