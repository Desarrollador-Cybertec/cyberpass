<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSysAdmin() || $user->role === 'org_admin';
    }

    public function view(User $user, User $target): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $target->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->isSysAdmin() || $user->role === 'org_admin';
    }

    public function update(User $user, User $target): bool
    {
        if ($user->isSysAdmin()) {
            return true;
        }

        return $user->role === 'org_admin'
            && $user->organization_id === $target->organization_id
            && ! $target->isSysAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        if ($target->isSysAdmin()) {
            return false;
        }

        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $target->organization_id);
    }
}
