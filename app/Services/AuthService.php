<?php

namespace App\Services;

use App\Helpers\OrganizationResolver;
use App\Helpers\TwoFactorPendingStore;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use PragmaRX\Google2FA\Google2FA;

class AuthService
{
    public function __construct(private Google2FA $google2fa) {}
    public function register(array $data): array
    {
        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'password'     => Hash::make($data['password']),
            'account_type' => 'personal',
            'role'         => 'org_user',
        ]);

        return [
            'user'  => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    public function attemptLogin(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Cuenta desactivada.'],
            ]);
        }

        $this->syncEnterpriseOrganization($user);

        if ($user->isEnterprise() && $user->two_factor_enabled) {
            return [
                'requires_2fa' => true,
                'temp_token'   => TwoFactorPendingStore::store($user->id),
            ];
        }

        $user->update(['last_login_at' => now()]);

        return [
            'user'  => $user->load('organization'),
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    public function completeTwoFactorLogin(string $tempToken, string $otp): array
    {
        $userId = TwoFactorPendingStore::retrieve($tempToken);

        if (! $userId) {
            throw ValidationException::withMessages([
                'temp_token' => ['Token temporal inválido o expirado.'],
            ]);
        }

        $user = User::findOrFail($userId);

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $otp)) {
            throw ValidationException::withMessages([
                'otp' => ['Código OTP incorrecto.'],
            ]);
        }

        TwoFactorPendingStore::forget($tempToken);
        $user->update(['last_login_at' => now()]);

        return [
            'user'  => $user->load('organization'),
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    public function findOrCreateFromGoogle(SocialiteUser $googleUser): array
    {
        $organization = OrganizationResolver::fromEmail($googleUser->getEmail());

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name'            => $googleUser->getName(),
                'email'           => $googleUser->getEmail(),
                'organization_id' => $organization?->id,
                'account_type'    => $organization ? 'enterprise' : 'personal',
                'role'            => 'org_user',
                'last_login_at'   => now(),
            ]
        );

        return [
            'user'               => $user->load('organization'),
            'token'              => $user->createToken('api')->plainTextToken,
            'requires_2fa_setup' => $user->isEnterprise() && ! $user->two_factor_enabled,
        ];
    }

    private function syncEnterpriseOrganization(User $user): void
    {
        if ($user->isSysAdmin() || $user->organization_id) {
            return;
        }

        $organization = OrganizationResolver::fromEmail($user->email);

        if ($organization) {
            $user->update([
                'organization_id' => $organization->id,
                'account_type'    => 'enterprise',
            ]);
            $user->refresh();
        }
    }
}
