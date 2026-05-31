<?php

namespace App\Observers;

use App\Actions\AdvanceBracketWinner;
use App\Concerns\BroadcastsQuietly;
use App\Events\GameStatusChanged;
use App\Models\Game;

class GameObserver
{
    use BroadcastsQuietly;

    public function __construct(private readonly AdvanceBracketWinner $advancer) {}

    /**
     * Broadcast a status transition — but only when `status` actually
     * changed. A plain schedule edit (date/location) updates the game too,
     * and we don't want those to masquerade as a kick-off / full-time on
     * the scoreboard.
     */
    public function updated(Game $game): void
    {
        if ($game->wasChanged('status')) {
            $this->broadcastQuietly(fn () => GameStatusChanged::dispatch($game));

            // Reaching Full Time on a knockout game promotes its winner to the
            // next round. No-ops for non-bracket games and draws.
            $this->advancer->execute($game);
        }
    }
}
