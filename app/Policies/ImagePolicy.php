<?php

namespace App\Policies;

use App\Models\Image;
use App\Models\User;

class ImagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Image $image): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSysAdmin();
    }

    public function update(User $user, Image $image): bool
    {
        return $user->isSysAdmin();
    }

    public function delete(User $user, Image $image): bool
    {
        return $user->isSysAdmin();
    }
}
