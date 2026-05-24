<?php

namespace App\Policies;

use App\Models\Stage;
use App\Models\User;

class StagePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Stage $stage): bool
    {
        return $stage->season->league->is_public || $user?->id === $stage->season->league->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Stage $stage): bool
    {
        return $user->id === $stage->season->league->user_id;
    }

    public function delete(User $user, Stage $stage): bool
    {
        return $user->id === $stage->season->league->user_id;
    }

    public function restore(User $user, Stage $stage): bool
    {
        return $user->id === $stage->season->league->user_id;
    }

    public function forceDelete(User $user, Stage $stage): bool
    {
        return $user->id === $stage->season->league->user_id;
    }
}
