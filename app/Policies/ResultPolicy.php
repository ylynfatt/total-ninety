<?php

namespace App\Policies;

use App\Models\League;
use App\Models\Result;
use App\Models\User;

class ResultPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Result $result): bool
    {
        $league = $this->leagueFor($result);

        if ($league === null) {
            return true;
        }

        return $league->is_public || $user?->id === $league->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(?User $user, Result $result): bool
    {
        $league = $this->leagueFor($result);

        if ($league === null) {
            return true;
        }

        return $user?->id === $league->user_id;
    }

    public function delete(?User $user, Result $result): bool
    {
        return $this->update($user, $result);
    }

    public function restore(User $user, Result $result): bool
    {
        $league = $this->leagueFor($result);

        return $league === null || $user->id === $league->user_id;
    }

    public function forceDelete(User $user, Result $result): bool
    {
        return $this->restore($user, $result);
    }

    /**
     * Resolve the owning league for a result via its game, preferring the
     * stage chain when set and falling back to the season FK.
     */
    private function leagueFor(Result $result): ?League
    {
        $game = $result->game;

        if ($game === null) {
            return null;
        }

        if ($game->stage_id !== null) {
            return $game->stage?->season?->league;
        }

        if ($game->season_id !== null) {
            return $game->season?->league;
        }

        return null;
    }
}
