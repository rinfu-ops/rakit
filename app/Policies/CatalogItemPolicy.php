<?php

namespace App\Policies;

use App\Domain\Catalog\Models\CatalogItem;
use App\Domain\System\Enums\UserRole;
use App\Models\User;

class CatalogItemPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, UserRole::cases(), true);
    }

    public function view(User $user, CatalogItem $catalogItem): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, CatalogItem $catalogItem): bool
    {
        return $this->canManage($user);
    }

    public function changeStatus(User $user, CatalogItem $catalogItem): bool
    {
        return $this->canManage($user);
    }

    public function merge(User $user, CatalogItem $catalogItem): bool
    {
        return $this->canManage($user);
    }

    public function importBaseline(User $user): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::CatalogManager], true);
    }
}
