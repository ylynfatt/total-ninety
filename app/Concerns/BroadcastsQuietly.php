<?php

namespace App\Concerns;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

trait BroadcastsQuietly
{
    /**
     * Dispatch a broadcast event without letting a transport failure (e.g. the
     * Reverb server being unreachable) bubble up and abort the request that
     * triggered it. Recording a result / event is the source of truth; the
     * realtime push is a best-effort side effect, so we log and move on.
     */
    protected function broadcastQuietly(Closure $dispatch): void
    {
        try {
            $dispatch();
        } catch (Throwable $e) {
            Log::warning('Realtime broadcast failed; the underlying change was still saved.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
