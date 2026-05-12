<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Models\User;

class CredentialVersionPolicy
{
    public function viewAny(User $user, Credential $credential): bool
    {
        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $credential->organization_id);
    }

    public function restore(User $user, CredentialVersion $version): bool
    {
        $credential = $version->credential;

        return $user->isSysAdmin()
            || ($user->role === 'org_admin' && $user->organization_id === $credential->organization_id);
    }
}
