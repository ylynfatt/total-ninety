<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Game $game): bool
    {
        // Games without a season (legacy data) are publicly viewable.
        if ($game->season_id === null) {
            return true;
        }

        return $game->season->league->is_public || $user?->id === $game->season->league->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(?User $user, Game $game): bool
    {
        // Legacy games (no season) remain editable while Phase 1 routes exist.
        if ($game->season_id === null) {
            return true;
        }

        return $user?->id === $game->season->league->user_id;
    }

    public function delete(?User $user, Game $game): bool
    {
        if ($game->season_id === null) {
            return true;
        }

        return $user?->id === $game->season->league->user_id;
    }

    public function restore(User $user, Game $game): bool
    {
        return $game->season_id === null
            || $user->id === $game->season->league->user_id;
    }

    public function forceDelete(User $user, Game $game): bool
    {
        return $game->season_id === null
            || $user->id === $game->season->league->user_id;
    }
}
