<?php

namespace App\Observers;

use App\Concerns\BroadcastsQuietly;
use App\Events\ScoreUpdated;
use App\Models\Result;

class ResultObserver
{
    use BroadcastsQuietly;

    /**
     * Fire ScoreUpdated whenever a result is created or its scores change.
     *
     * `saved` covers both insert and update. The game relation is loaded so
     * the event's payload can read current_minute + status off it.
     */
    public function saved(Result $result): void
    {
        $game = $result->game;

        if ($game === null) {
            return;
        }

        // Make sure the event payload reflects the just-saved scores rather
        // than a stale relation cached on the game.
        $game->setRelation('result', $result);

        $this->broadcastQuietly(fn () => ScoreUpdated::dispatch($game));
    }

    /**
     * A cleared result still changes what the scoreboard should show, so
     * broadcast on delete too. The game's result relation is now empty,
     * which the payload handles as null scores.
     */
    public function deleted(Result $result): void
    {
        $game = $result->game;

        if ($game === null) {
            return;
        }

        $game->setRelation('result', null);

        $this->broadcastQuietly(fn () => ScoreUpdated::dispatch($game));
    }
}
