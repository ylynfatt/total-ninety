<?php

namespace App\Policies;

use App\Models\Season;
use App\Models\User;

class SeasonPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Season $season): bool
    {
        return $season->league->is_public || $user?->id === $season->league->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Season $season): bool
    {
        return $user->id === $season->league->user_id;
    }

    public function delete(User $user, Season $season): bool
    {
        return $user->id === $season->league->user_id;
    }

    public function restore(User $user, Season $season): bool
    {
        return $user->id === $season->league->user_id;
    }

    public function forceDelete(User $user, Season $season): bool
    {
        return $user->id === $season->league->user_id;
    }
}
