<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a game transitions between lifecycle states
 * (scheduled → live → half_time → full_time, etc.).
 *
 * Goes out on both game.{id} (the gamecast flips its live/final banner)
 * and scoreboard.live (a game appearing/disappearing from the live list).
 */
class GameStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("game.{$this->game->id}"),
            new Channel('scoreboard.live'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GameStatusChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'status' => $this->game->status->value,
            'current_minute' => $this->game->current_minute,
        ];
    }
}
