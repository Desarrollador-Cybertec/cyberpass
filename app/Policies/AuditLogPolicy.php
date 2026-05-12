<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSysAdmin() || $user->isOrgAdmin();
    }

    public function viewOrganization(User $user, Organization $organization): bool
    {
        return $user->isSysAdmin()
            || ($user->isOrgAdmin() && $user->organization_id === $organization->id);
    }
}
