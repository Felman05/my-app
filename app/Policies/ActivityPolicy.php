<?php

namespace App\Policies;

use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [User::ROLE_PROVIDER, User::ROLE_ADMIN, User::ROLE_LGU, User::ROLE_TOURIST], true);
    }

    public function create(User $user): bool
    {
        return $user->role === User::ROLE_PROVIDER;
    }

    public function moderate(User $user): bool
    {
        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_LGU], true);
    }
}
