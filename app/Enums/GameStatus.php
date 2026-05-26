<?php

namespace App\Enums;

enum GameStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case HalfTime = 'half_time';
    case FullTime = 'full_time';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Live => 'Live',
            self::HalfTime => 'Half Time',
            self::FullTime => 'Full Time',
            self::Postponed => 'Postponed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the game is actively in progress (live or in half-time break).
     * Used by the live scoreboard to filter games.
     */
    public function isInProgress(): bool
    {
        return match ($this) {
            self::Live, self::HalfTime => true,
            default => false,
        };
    }

    /**
     * Whether the game has reached a definitive end state.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::FullTime, self::Cancelled => true,
            default => false,
        };
    }
}
