<?php

namespace App\Actions;

use App\Enums\GameEventType;
use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
 * Each phase transition also drops a team-less lifecycle event (Kick Off, Half
 * Time, Full Time) onto the timeline as a phase marker. Administrative
 * statuses (Postponed, Cancelled, Scheduled) leave no marker.
 *
 * `$minuteOverride` is the base minute to (re)start or freeze at:
 *   - Kick Off passes 0; Resume passes null (continue from the frozen minute).
 *   - A manual correction passes the corrected minute (re-anchoring a running
 *     clock so it keeps ticking from there).
 */
class ApplyGameStatus
{
    /**
     * The minute the second half kicks off from. Resuming after Half Time
     * snaps the clock back to 45' (per convention) rather than continuing from
     * whatever stoppage minute the first half froze at.
     */
    private const SECOND_HALF_START = 45;

    public function execute(Game $game, GameStatus $status, ?int $minuteOverride = null): Game
    {
        $previousStatus = $game->status;

        return DB::transaction(function () use ($game, $status, $minuteOverride, $previousStatus): Game {
            if ($status === GameStatus::Live) {
                $game->forceFill([
                    'status' => $status,
                    'current_minute' => $minuteOverride ?? $this->liveStartMinute($game),
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

            $this->recordLifecycleMarker($game, $previousStatus, $status);

            return $game;
        });
    }

    /**
     * Drop a phase marker on the timeline when the status actually changes.
     * Kick Off / Resume both mark a kick-off (the second half carries its 45'),
     * Half Time and Full Time mark the break at the frozen minute. Other
     * statuses are administrative and leave no marker.
     */
    private function recordLifecycleMarker(Game $game, GameStatus $from, GameStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        $type = match ($to) {
            GameStatus::Live => GameEventType::KickOff,
            GameStatus::HalfTime => GameEventType::HalfTime,
            GameStatus::FullTime => GameEventType::FullTime,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $game->events()->create([
            'type' => $type,
            // The opening kick off has no meaningful minute; everything else
            // marks the minute it happened at.
            'minute' => $type === GameEventType::KickOff ? ($game->current_minute ?: null) : $game->current_minute,
            'team_id' => null,
        ]);
    }

    /**
     * The base minute to start the clock from when there's no explicit
     * override: 45' when resuming the second half after Half Time, otherwise
     * the game's current minute (0 for a fresh kick off).
     */
    private function liveStartMinute(Game $game): int
    {
        if ($game->status === GameStatus::HalfTime) {
            return self::SECOND_HALF_START;
        }

        return $game->current_minute ?? 0;
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
