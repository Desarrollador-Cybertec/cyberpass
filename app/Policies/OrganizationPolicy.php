<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSysAdmin();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->isSysAdmin()
            || $user->organization_id === $organization->id;
    }

    public function create(User $user): bool
    {
        return $user->isSysAdmin();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $organization->id);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isSysAdmin();
    }

    public function manageDomains(User $user, Organization $organization): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $organization->id);
    }

    public function manageUsers(User $user, Organization $organization): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $organization->id);
    }

    public function manageDivisions(User $user, Organization $organization): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $organization->id);
    }
}
