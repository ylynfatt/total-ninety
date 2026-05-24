<?php

namespace App\Policies;

use App\Models\League;
use App\Models\User;

class LeaguePolicy
{
    /**
     * Anyone (including guests) may browse league listings.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Public leagues are visible to everyone. Private leagues are only
     * visible to their owner.
     */
    public function view(?User $user, League $league): bool
    {
        return $league->is_public || $user?->id === $league->user_id;
    }

    /**
     * Any authenticated user may create a new league.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, League $league): bool
    {
        return $user->id === $league->user_id;
    }

    public function delete(User $user, League $league): bool
    {
        return $user->id === $league->user_id;
    }

    public function restore(User $user, League $league): bool
    {
        return $user->id === $league->user_id;
    }

    public function forceDelete(User $user, League $league): bool
    {
        return $user->id === $league->user_id;
    }
}
