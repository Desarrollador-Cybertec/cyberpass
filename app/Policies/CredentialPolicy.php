<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Credential;
use App\Models\User;

class CredentialPolicy
{
    public function viewAny(User $user, Category $category): bool
    {
        return $user->isSysAdmin() || $user->organization_id === $category->organization_id;
    }

    public function view(User $user, Credential $credential): bool
    {
        return $user->isSysAdmin() || $user->organization_id === $credential->organization_id;
    }

    public function create(User $user, Category $category): bool
    {
        return $user->isSysAdmin() || $user->organization_id === $category->organization_id;
    }

    public function update(User $user, Credential $credential): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $credential->organization_id)
            || ($user->role === 'org_user' && $user->id === $credential->created_by);
    }

    public function delete(User $user, Credential $credential): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $credential->organization_id);
    }
}
