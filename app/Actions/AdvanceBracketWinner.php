<?php

namespace App\Actions;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Validation\ValidationException;

/**
 * Keep an unplayed next-round slot synchronized with the finalized winner.
 * Removing a result or reopening a match clears its previous advancement.
 * Once the next match has recorded data, changing its participants is blocked.
 */
class AdvanceBracketWinner
{
    public function execute(Game $game): void
    {
        if ($game->round === null || $game->bracket_position === null) {
            return;
        }

        $winnerId = $game->status === GameStatus::FullTime ? $this->winnerTeamId($game) : null;

        $nextGame = Game::query()
            ->where('stage_id', $game->stage_id)
            ->where('round', $game->round + 1)
            ->where('bracket_position', intdiv($game->bracket_position, 2))
            ->first();

        if ($nextGame === null) {
            return; // This was the final — nobody to advance to.
        }

        $slot = $game->bracket_position % 2 === 0 ? 'home_team_id' : 'away_team_id';

        if ($nextGame->{$slot} === $winnerId) {
            return;
        }

        if ($nextGame->status !== GameStatus::Scheduled || $nextGame->result()->exists() || $nextGame->events()->exists()) {
            throw ValidationException::withMessages(['result' => 'The next-round match has already started or has recorded data. Resolve it before changing the advancing team.']);
        }

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

        return match ($result->winnerSide()) {
            'home' => $game->home_team_id,
            'away' => $game->away_team_id,
            default => null,
        };
    }
}
