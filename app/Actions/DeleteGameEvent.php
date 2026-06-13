<?php

namespace App\Actions;

use App\Concerns\CreditsGoals;
use App\Models\Game;
use App\Models\GameEvent;
use Illuminate\Support\Facades\DB;

/**
 * Remove a timeline event, walking back its scoreline contribution so deleting
 * a mistaken goal also takes the point off the board.
 */
class DeleteGameEvent
{
    use CreditsGoals;

    public function execute(Game $game, GameEvent $event): void
    {
        DB::transaction(function () use ($game, $event): void {
            $side = $this->creditedSide($game, $event->type, $event->team_id);

            $event->delete();

            $this->adjustScoreline($game, $side, -1);
        });
    }
}
