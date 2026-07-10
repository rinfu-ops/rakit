<?php

namespace App\Policies;

use App\Domain\System\Enums\UserRole;
use App\Models\User;

class SystemOperationalModePolicy
{
    public function change(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
