<?php

namespace App\Services;

use App\Mail\OrganizationInvitationMail;
use App\Models\Division;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        $token = Str::random(64);

        $user = DB::transaction(function () use ($organization, $data, $token) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'         => $data['name'],
                    'password'     => Hash::make(Str::random(32)),
                    'account_type' => 'enterprise',
                    'is_active'    => false,
                ]
            );

            // Asignar campos privilegiados solo via forceFill (no mass assignment)
            if ($user->wasRecentlyCreated) {
                $user->forceFill([
                    'organization_id' => $organization->id,
                    'role'            => $data['role'],
                ])->save();
            }

            OrganizationInvitation::updateOrCreate(
                ['email' => $user->email],
                [
                    'organization_id' => $organization->id,
                    'role'            => $data['role'],
                    'token_hash'      => Hash::make($token),
                    'expires_at'      => Carbon::now()->addDays(7),
                    'created_at'      => Carbon::now(),
                ]
            );

            return $user;
        });

        Mail::to($user->email)->send(new OrganizationInvitationMail($user, $organization, $token));

        return $user;
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

    public function suspendUser(User $user): void
    {
        $user->update(['is_active' => false]);
    }

    public function activateUser(User $user): void
    {
        $user->update(['is_active' => true]);
    }

    public function changeRole(User $user, string $role): void
    {
        $user->update(['role' => $role]);
    }

    public function removeUser(User $user): void
    {
        $user->update(['organization_id' => null, 'account_type' => 'personal']);
    }

    public function createDivision(Organization $organization, array $data): Division
    {
        return $organization->divisions()->create($data);
    }

    public function updateDivision(Division $division, array $data): Division
    {
        $division->update($data);

        return $division->fresh();
    }

    public function deleteDivision(Division $division): void
    {
        $division->delete();
    }
}
