<?php

namespace App\Observers;

use App\Events\GameEventRecorded;
use App\Models\GameEvent;

class GameEventObserver
{
    /**
     * Push a newly-recorded timeline entry out to the gamecast.
     */
    public function created(GameEvent $gameEvent): void
    {
        GameEventRecorded::dispatch($gameEvent);
    }
}
