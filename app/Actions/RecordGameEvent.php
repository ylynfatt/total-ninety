<?php

namespace App\Actions;

use App\Concerns\CreditsGoals;
use App\Models\Game;
use App\Models\GameEvent;
use Illuminate\Support\Facades\DB;

/**
 * Record a single timeline event on a game and, for scoring events, keep the
 * Result scoreline in step.
 *
 * The whole thing runs in a transaction so a goal never lands on the timeline
 * without its score (or vice versa).
 *
 * The separate manual Result editor remains the path for historical or
 * correction entry — this action only fires from the live gamecast editor.
 */
class RecordGameEvent
{
    use CreditsGoals;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Game $game, array $attributes): GameEvent
    {
        return DB::transaction(function () use ($game, $attributes): GameEvent {
            $event = $game->events()->create($attributes);

            $this->adjustScoreline($game, $this->creditedSide($game, $event->type, $event->team_id), 1);

            return $event;
        });
    }
}
