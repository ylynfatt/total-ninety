<?php

namespace App\Actions;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Support\Carbon;

/**
 * Drive a game's lifecycle status and its match clock together.
 *
 * The clock is a running timer rather than a manually-typed number: while a
 * game is Live we store the minute it started from (current_minute) plus the
 * wall-clock instant it started (clock_started_at), and every client ticks the
 * displayed minute locally from those two values. Pausing (half time, full
 * time, …) freezes the clock by writing the elapsed minute back into
 * current_minute and clearing clock_started_at.
 *
 * `$minuteOverride` is the base minute to (re)start or freeze at:
 *   - Kick Off passes 0; Resume passes null (continue from the frozen minute).
 *   - A manual correction passes the corrected minute (re-anchoring a running
 *     clock so it keeps ticking from there).
 */
class ApplyGameStatus
{
    public function execute(Game $game, GameStatus $status, ?int $minuteOverride = null): Game
    {
        if ($status === GameStatus::Live) {
            $game->forceFill([
                'status' => $status,
                'current_minute' => $minuteOverride ?? $game->current_minute ?? 0,
                'clock_started_at' => Carbon::now(),
            ]);
        } else {
            $game->forceFill([
                'status' => $status,
                'current_minute' => $minuteOverride ?? $this->runningMinute($game),
                'clock_started_at' => null,
            ]);
        }

        $game->save();

        return $game;
    }

    /**
     * The minute the clock currently reads: the stored base plus whole minutes
     * elapsed since it started, or just the base when the clock isn't running.
     */
    private function runningMinute(Game $game): int
    {
        $base = $game->current_minute ?? 0;

        if ($game->status !== GameStatus::Live || $game->clock_started_at === null) {
            return $base;
        }

        return $base + intdiv($game->clock_started_at->diffInSeconds(Carbon::now()), 60);
    }
}
