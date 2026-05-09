<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Organization;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->isSysAdmin() || $user->organization_id === $organization->id;
    }

    public function view(User $user, Category $category): bool
    {
        return $user->isSysAdmin() || $user->organization_id === $category->organization_id;
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $organization->id);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $category->organization_id);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $category->organization_id);
    }
}
