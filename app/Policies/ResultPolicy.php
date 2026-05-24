<?php

namespace App\Policies;

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
        $season = $result->game->season;

        if ($season === null) {
            return true;
        }

        return $season->league->is_public || $user?->id === $season->league->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(?User $user, Result $result): bool
    {
        $season = $result->game->season;

        if ($season === null) {
            return true;
        }

        return $user?->id === $season->league->user_id;
    }

    public function delete(?User $user, Result $result): bool
    {
        $season = $result->game->season;

        if ($season === null) {
            return true;
        }

        return $user?->id === $season->league->user_id;
    }

    public function restore(User $user, Result $result): bool
    {
        $season = $result->game->season;

        return $season === null || $user->id === $season->league->user_id;
    }

    public function forceDelete(User $user, Result $result): bool
    {
        $season = $result->game->season;

        return $season === null || $user->id === $season->league->user_id;
    }
}
