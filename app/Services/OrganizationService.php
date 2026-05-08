<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrganizationService
{
    public function create(array $data): Organization
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return Organization::create($data);
    }

    public function update(Organization $organization, array $data): Organization
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $organization->update($data);

        return $organization->fresh();
    }

    public function delete(Organization $organization): void
    {
        $organization->delete();
    }

    public function addDomain(Organization $organization, string $domain): OrganizationDomain
    {
        return $organization->domains()->create([
            'domain'      => strtolower($domain),
            'is_verified' => false,
        ]);
    }

    public function removeDomain(OrganizationDomain $domain): void
    {
        $domain->delete();
    }

    public function inviteUser(Organization $organization, array $data): User
    {
        return User::create([
            'organization_id' => $organization->id,
            'name'            => $data['name'],
            'email'           => $data['email'],
            'password'        => Hash::make(Str::random(16)),
            'role'            => $data['role'],
            'account_type'    => 'enterprise',
            'is_active'       => true,
        ]);
    }

    public function updateUser(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function deactivateUser(User $user): void
    {
        $user->update(['is_active' => false]);
    }

    public function removeUser(User $user): void
    {
        $user->update(['organization_id' => null, 'account_type' => 'personal']);
    }
}
