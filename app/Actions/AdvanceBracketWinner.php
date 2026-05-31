<?php

namespace App\Actions;

use App\Enums\GameStatus;
use App\Models\Game;

/**
 * Promote the winner of a finalized knockout game into its next-round slot.
 *
 * A game decides its parent slot once it reaches Full Time with a decisive
 * result. The winner of round r position p advances to round r+1 position
 * p/2 — into the home slot when p is even, the away slot when p is odd
 * (the inverse of how the generator lays children out).
 *
 * Idempotent: re-running after a result correction simply overwrites the
 * next slot with the (possibly new) winner. No-ops for non-bracket games,
 * games that aren't final yet, draws, and the final itself.
 */
class AdvanceBracketWinner
{
    public function execute(Game $game): void
    {
        if ($game->round === null || $game->bracket_position === null) {
            return;
        }

        if ($game->status !== GameStatus::FullTime) {
            return;
        }

        $winnerId = $this->winnerTeamId($game);

        if ($winnerId === null) {
            return;
        }

        $nextGame = Game::query()
            ->where('stage_id', $game->stage_id)
            ->where('round', $game->round + 1)
            ->where('bracket_position', intdiv($game->bracket_position, 2))
            ->first();

        if ($nextGame === null) {
            return; // This was the final — nobody to advance to.
        }

        $slot = $game->bracket_position % 2 === 0 ? 'home_team_id' : 'away_team_id';

        $nextGame->update([$slot => $winnerId]);
    }

    /**
     * The winning team's id, or null when there's no decisive result yet
     * (missing result or a draw).
     */
    private function winnerTeamId(Game $game): ?int
    {
        $result = $game->result;

        if ($result === null) {
            return null;
        }

        if ($result->home_team_score === $result->away_team_score) {
            return null;
        }

        return $result->home_team_score > $result->away_team_score
            ? $game->home_team_id
            : $game->away_team_id;
    }
}
