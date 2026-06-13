<?php

namespace App\Actions;

use App\Concerns\CreditsGoals;
use App\Models\Game;
use App\Models\GameEvent;
use Illuminate\Support\Facades\DB;

/**
 * Correct an existing timeline event, reconciling the scoreline when the edit
 * changes the event's scoring impact (e.g. fixing the credited team, or
 * turning a mis-typed Goal into a Yellow Card).
 *
 * The old contribution is reversed and the new one applied; when the scoring
 * impact is unchanged the two cancel out and the score is left untouched.
 */
class UpdateGameEvent
{
    use CreditsGoals;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Game $game, GameEvent $event, array $attributes): GameEvent
    {
        return DB::transaction(function () use ($game, $event, $attributes): GameEvent {
            $previousSide = $this->creditedSide($game, $event->type, $event->team_id);

            $event->update($attributes);

            $newSide = $this->creditedSide($game, $event->type, $event->team_id);

            $this->adjustScoreline($game, $previousSide, -1);
            $this->adjustScoreline($game, $newSide, 1);

            return $event;
        });
    }
}
