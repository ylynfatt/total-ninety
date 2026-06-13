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
     * Broadcast a status or clock change so the gamecast and scoreboard stay
     * in step. Restricted to the live-control columns (status, current_minute,
     * clock_started_at) so a plain schedule edit (date/location) doesn't
     * masquerade as a kick-off / clock update on the scoreboard.
     */
    public function updated(Game $game): void
    {
        if ($game->wasChanged(['status', 'current_minute', 'clock_started_at'])) {
            $this->broadcastQuietly(fn () => GameStatusChanged::dispatch($game));
        }

        if ($game->wasChanged('status')) {
            // Reaching Full Time on a knockout game promotes its winner to the
            // next round. No-ops for non-bracket games and draws.
            $this->advancer->execute($game);
        }
    }
}
