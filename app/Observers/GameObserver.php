<?php

namespace App\Observers;

use App\Events\GameStatusChanged;
use App\Models\Game;

class GameObserver
{
    /**
     * Broadcast a status transition — but only when `status` actually
     * changed. A plain schedule edit (date/location) updates the game too,
     * and we don't want those to masquerade as a kick-off / full-time on
     * the scoreboard.
     */
    public function updated(Game $game): void
    {
        if ($game->wasChanged('status')) {
            GameStatusChanged::dispatch($game);
        }
    }
}
