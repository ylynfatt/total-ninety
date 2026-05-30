<?php

namespace App\Observers;

use App\Concerns\BroadcastsQuietly;
use App\Events\GameEventRecorded;
use App\Models\GameEvent;

class GameEventObserver
{
    use BroadcastsQuietly;

    /**
     * Push a newly-recorded timeline entry out to the gamecast.
     */
    public function created(GameEvent $gameEvent): void
    {
        $this->broadcastQuietly(fn () => GameEventRecorded::dispatch($gameEvent));
    }
}
