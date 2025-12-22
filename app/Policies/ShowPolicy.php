<?php

namespace App\Policies;

use App\Models\{User, Show};

class ShowPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Show $show): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSupervisor();
    }

    public function update(User $user, Show $show): bool
    {
        return $user->isSupervisor();
    }

    public function delete(User $user, Show $show): bool
    {
        return $user->isSupervisor();
    }

    public function control(User $user, Show $show): bool
    {
        return $user->isSupervisor();
    }
}
