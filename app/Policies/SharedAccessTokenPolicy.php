<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\SharedAccessToken;
use App\Models\User;

class SharedAccessTokenPolicy
{
    public function viewAny(User $user, Credential $credential): bool
    {
        if ($user->isPersonalUser()) {
            return false;
        }

        if ($user->isSysAdmin()) {
            return true;
        }

        return $user->organization_id === $credential->organization_id;
    }

    public function create(User $user, Credential $credential): bool
    {
        if ($user->isPersonalUser()) {
            return false;
        }

        if ($user->isSysAdmin()) {
            return true;
        }

        if ($user->organization_id !== $credential->organization_id) {
            return false;
        }

        return $user->isOrgAdmin() || $user->isOrgUser();
    }

    public function revoke(User $user, SharedAccessToken $token): bool
    {
        if ($user->isPersonalUser()) {
            return false;
        }

        if ($user->isSysAdmin()) {
            return true;
        }

        $credential = $token->credential;

        if ($user->organization_id !== $credential->organization_id) {
            return false;
        }

        if ($user->isOrgAdmin()) {
            return true;
        }

        return $token->created_by === $user->id;
    }
}
