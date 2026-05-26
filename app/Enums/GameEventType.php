<?php

namespace App\Enums;

enum GameEventType: string
{
    case KickOff = 'kick_off';
    case Goal = 'goal';
    case OwnGoal = 'own_goal';
    case PenaltyGoal = 'penalty_goal';
    case YellowCard = 'yellow_card';
    case RedCard = 'red_card';
    case Substitution = 'substitution';
    case HalfTime = 'half_time';
    case FullTime = 'full_time';
    case VarCheck = 'var_check';
    case Commentary = 'commentary';

    public function label(): string
    {
        return match ($this) {
            self::KickOff => 'Kick Off',
            self::Goal => 'Goal',
            self::OwnGoal => 'Own Goal',
            self::PenaltyGoal => 'Penalty Goal',
            self::YellowCard => 'Yellow Card',
            self::RedCard => 'Red Card',
            self::Substitution => 'Substitution',
            self::HalfTime => 'Half Time',
            self::FullTime => 'Full Time',
            self::VarCheck => 'VAR Check',
            self::Commentary => 'Commentary',
        };
    }

    /**
     * Whether this event type counts toward a team's score.
     */
    public function isScoringEvent(): bool
    {
        return match ($this) {
            self::Goal, self::OwnGoal, self::PenaltyGoal => true,
            default => false,
        };
    }

    /**
     * Whether this event type marks a transition in the game's lifecycle
     * (kickoff, half time, full time). These are typically created
     * automatically alongside a status change.
     */
    public function isLifecycleEvent(): bool
    {
        return match ($this) {
            self::KickOff, self::HalfTime, self::FullTime => true,
            default => false,
        };
    }
}
