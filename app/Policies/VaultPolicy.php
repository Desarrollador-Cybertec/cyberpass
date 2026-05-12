<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Credential;
use App\Models\User;

class VaultPolicy
{
    // Category gates
    public function viewAnyCategory(User $user): bool
    {
        return true;
    }

    public function viewCategory(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
    }

    public function createCategory(User $user): bool
    {
        return true;
    }

    public function updateCategory(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
    }

    public function deleteCategory(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
    }

    // Credential gates
    public function viewAnyCredential(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
    }

    public function viewCredential(User $user, Credential $credential): bool
    {
        return $credential->user_id === $user->id;
    }

    public function createCredential(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
    }

    public function updateCredential(User $user, Credential $credential): bool
    {
        return $credential->user_id === $user->id;
    }

    public function deleteCredential(User $user, Credential $credential): bool
    {
        return $credential->user_id === $user->id;
    }
}
