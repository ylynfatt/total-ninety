<?php

namespace App\Events;

use App\Models\GameEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a new GameEvent (goal, card, sub, commentary, …) is recorded.
 *
 * Goes out only on game.{id} — the gamecast timeline subscribes here. The
 * global scoreboard doesn't need individual timeline entries; it reacts to
 * ScoreUpdated / GameStatusChanged instead.
 */
class GameEventRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GameEvent $event) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("game.{$this->event->game_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GameEventRecorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->event->id,
            'game_id' => $this->event->game_id,
            'minute' => $this->event->minute,
            'stoppage' => $this->event->stoppage,
            'type' => $this->event->type->value,
            'team_id' => $this->event->team_id,
            'player_id' => $this->event->player_id,
            'assist_player_id' => $this->event->assist_player_id,
            'secondary_player_id' => $this->event->secondary_player_id,
            'description' => $this->event->description,
        ];
    }
}
