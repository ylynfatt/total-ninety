<?php

namespace App\Domain\Standings;

/**
 * One row in a standings table — a snapshot of a single team's tournament
 * record at the moment the calculator was run. Immutable; build a new row
 * when something changes.
 *
 * goal_difference is precomputed at construction time so views can sort/
 * display it without re-deriving from goals_for / goals_against.
 *
 * form is a short string of the team's most recent results, newest first
 * (e.g. "WWDLW" means the team's most recent five games were win, win,
 * draw, loss, win). Empty when the team hasn't played any games yet.
 */
final readonly class StandingRow
{
    public int $goal_difference;

    public function __construct(
        public int $team_id,
        public string $team_name,
        public string $team_acronym,
        public int $played,
        public int $won,
        public int $drawn,
        public int $lost,
        public int $goals_for,
        public int $goals_against,
        public int $points,
        public string $form,
    ) {
        $this->goal_difference = $goals_for - $goals_against;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'team_id' => $this->team_id,
            'team_name' => $this->team_name,
            'team_acronym' => $this->team_acronym,
            'played' => $this->played,
            'won' => $this->won,
            'drawn' => $this->drawn,
            'lost' => $this->lost,
            'goals_for' => $this->goals_for,
            'goals_against' => $this->goals_against,
            'goal_difference' => $this->goal_difference,
            'points' => $this->points,
            'form' => $this->form,
        ];
    }
}
