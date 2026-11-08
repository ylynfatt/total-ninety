<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Team $team): bool
    {
        return $team->user_id !== null && $team->user_id === $user->id;
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }
}
