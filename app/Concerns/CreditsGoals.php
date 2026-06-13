<?php

namespace App\Concerns;

use App\Enums\GameEventType;
use App\Models\Game;
use App\Models\Result;

/**
 * Shared scoreline bookkeeping for the actions that create, edit, and delete
 * scoring events. Keeping the "which side does this goal credit" rule and the
 * clamped increment in one place means recording a goal and later correcting
 * or removing it can never drift out of agreement.
 */
trait CreditsGoals
{
    /**
     * Which side of the scoreline a scoring event credits: 'home', 'away', or
     * null when the event doesn't score or names a team that isn't playing.
     *
     * `team_id` is always the team of the player involved, so a regular Goal /
     * PenaltyGoal credits that team while an OwnGoal credits the opponent.
     */
    protected function creditedSide(Game $game, GameEventType $type, ?int $teamId): ?string
    {
        if (! $type->isScoringEvent() || $teamId === null) {
            return null;
        }

        $creditedTeamId = $type === GameEventType::OwnGoal
            ? $this->opponentTeamId($game, $teamId)
            : $teamId;

        return match ($creditedTeamId) {
            $game->home_team_id => 'home',
            $game->away_team_id => 'away',
            default => null,
        };
    }

    /**
     * Nudge one side's score by $delta (never below zero), creating the Result
     * row on demand. A no-op when $side is null.
     */
    protected function adjustScoreline(Game $game, ?string $side, int $delta): void
    {
        if ($side === null || $delta === 0) {
            return;
        }

        $result = Result::firstOrCreate(
            ['game_id' => $game->id],
            ['home_team_score' => 0, 'away_team_score' => 0],
        );

        $column = $side === 'home' ? 'home_team_score' : 'away_team_score';

        $result->update([
            $column => max(0, (int) $result->{$column} + $delta),
        ]);
    }

    private function opponentTeamId(Game $game, ?int $teamId): ?int
    {
        return match ($teamId) {
            $game->home_team_id => $game->away_team_id,
            $game->away_team_id => $game->home_team_id,
            default => null,
        };
    }
}
