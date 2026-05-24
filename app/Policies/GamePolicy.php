<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\League;
use App\Models\User;

class GamePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Game $game): bool
    {
        $league = $this->leagueFor($game);

        if ($league === null) {
            return true;
        }

        return $league->is_public || $user?->id === $league->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(?User $user, Game $game): bool
    {
        $league = $this->leagueFor($game);

        // Legacy games (no stage, no season) remain mutable while Phase 1
        // routes still exist.
        if ($league === null) {
            return true;
        }

        return $user?->id === $league->user_id;
    }

    public function delete(?User $user, Game $game): bool
    {
        return $this->update($user, $game);
    }

    public function restore(User $user, Game $game): bool
    {
        $league = $this->leagueFor($game);

        return $league === null || $user->id === $league->user_id;
    }

    public function forceDelete(User $user, Game $game): bool
    {
        return $this->restore($user, $game);
    }

    /**
     * Resolve the owning league for a game, preferring the stage chain
     * when set and falling back to the season FK.
     */
    private function leagueFor(Game $game): ?League
    {
        if ($game->stage_id !== null) {
            return $game->stage?->season?->league;
        }

        if ($game->season_id !== null) {
            return $game->season?->league;
        }

        return null;
    }
}
