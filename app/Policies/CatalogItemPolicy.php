<?php

namespace App\Policies;

use App\Domain\System\Enums\UserRole;
use App\Models\User;

class CatalogItemPolicy
{
    public function importBaseline(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::CatalogManager], true);
    }
}
