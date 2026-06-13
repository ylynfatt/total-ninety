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

    /**
     * A correction to an existing entry — the gamecast reloads its timeline.
     */
    public function updated(GameEvent $gameEvent): void
    {
        $this->broadcastQuietly(fn () => GameEventRecorded::dispatch($gameEvent));
    }

    /**
     * A removed entry — same scoped reload drops it from the live timeline.
     */
    public function deleted(GameEvent $gameEvent): void
    {
        $this->broadcastQuietly(fn () => GameEventRecorded::dispatch($gameEvent));
    }
}
