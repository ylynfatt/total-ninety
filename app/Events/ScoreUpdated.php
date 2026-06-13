<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a game's Result is created or changed.
 *
 * Goes out on two public channels:
 *   - game.{id}        — the per-game gamecast page subscribes to this
 *   - scoreboard.live  — the global live scoreboard subscribes to this
 *
 * ShouldBroadcastNow keeps it synchronous (no queue worker needed) so the
 * latency between recording a result and the UI updating stays low.
 */
class ScoreUpdated implements ShouldBroadcastNow
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
        return 'ScoreUpdated';
    }

    /**
     * Tight payload — just what the scoreboard / gamecast need to re-render,
     * not the whole model graph.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $result = $this->game->result;

        return [
            'game_id' => $this->game->id,
            'home_team_score' => $result?->home_team_score,
            'away_team_score' => $result?->away_team_score,
            'current_minute' => $this->game->current_minute,
            'clock_started_at' => $this->game->clock_started_at?->toISOString(),
            'status' => $this->game->status->value,
        ];
    }
}
