<?php

namespace App\Enums;

enum StageFormat: string
{
    case RoundRobinSingle = 'round_robin_single';
    case RoundRobinDouble = 'round_robin_double';
    case GroupStage = 'group_stage';
    case SingleElimination = 'single_elimination';
    case DoubleElimination = 'double_elimination';
    case Conference = 'conference';

    /**
     * Whether this format produces a standings table at the end (vs. a bracket).
     */
    public function isTable(): bool
    {
        return match ($this) {
            self::RoundRobinSingle,
            self::RoundRobinDouble,
            self::GroupStage,
            self::Conference => true,
            self::SingleElimination,
            self::DoubleElimination => false,
        };
    }

    /**
     * Whether this format produces a bracket (vs. a standings table).
     */
    public function isBracket(): bool
    {
        return ! $this->isTable();
    }

    /**
     * Whether this format slices teams into groups/conferences.
     */
    public function hasGroups(): bool
    {
        return match ($this) {
            self::GroupStage, self::Conference => true,
            default => false,
        };
    }

    /**
     * Human-friendly label for UI rendering.
     */
    public function label(): string
    {
        return match ($this) {
            self::RoundRobinSingle => 'Round Robin (Single)',
            self::RoundRobinDouble => 'Round Robin (Home & Away)',
            self::GroupStage => 'Group Stage',
            self::SingleElimination => 'Single Elimination',
            self::DoubleElimination => 'Double Elimination',
            self::Conference => 'Conference + Playoffs',
        };
    }
}
