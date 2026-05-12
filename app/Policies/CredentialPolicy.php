<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Credential;
use App\Models\User;

class CredentialPolicy
{
    public function viewAny(User $user, Category $category): bool
    {
        if ($user->isPersonalUser()) {
            return false;
        }

        return $user->isSysAdmin() || $user->organization_id === $category->organization_id;
    }

    public function view(User $user, Credential $credential): bool
    {
        if ($user->isPersonalUser()) {
            return false;
        }

        return $user->isSysAdmin() || $user->organization_id === $credential->organization_id;
    }

    public function create(User $user, Category $category): bool
    {
        if ($user->isPersonalUser()) {
            return false;
        }

        return $user->isSysAdmin() || $user->organization_id === $category->organization_id;
    }

    public function update(User $user, Credential $credential): bool
    {
        return $user->isSysAdmin()
            || ($user->isOrgAdmin() && $user->organization_id === $credential->organization_id)
            || ($user->isOrgUser() && $user->id === $credential->created_by);
    }

    public function delete(User $user, Credential $credential): bool
    {
        return $user->isSysAdmin()
            || ($user->isOrgAdmin() && $user->organization_id === $credential->organization_id);
    }
}
