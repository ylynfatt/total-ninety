<?php

namespace App\Domain\Standings;

/**
 * One row in a cross-group "best Nth-placed teams" ranking — a team's
 * StandingRow from its own group table, tagged with the group it came from
 * so the ranking can show (and later seed by) group identity.
 */
final readonly class BestPlacedRow
{
    public function __construct(
        public int $group_id,
        public string $group_name,
        public StandingRow $row,
    ) {
        //
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'group_id' => $this->group_id,
            'group_name' => $this->group_name,
            ...$this->row->toArray(),
        ];
    }
}
