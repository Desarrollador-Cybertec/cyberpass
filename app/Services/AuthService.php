<?php

namespace App\Services;

use App\Helpers\OrganizationResolver;
use App\Helpers\PublicEmailDetector;
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
        $email        = $data['email'];
        $isCorporate  = PublicEmailDetector::isCorporate($email);
        $organization = $isCorporate ? OrganizationResolver::fromEmail($email) : null;

        $user = User::create([
            'name'            => $data['name'],
            'email'           => $email,
            'password'        => Hash::make($data['password']),
            'account_type'    => $organization ? 'enterprise' : ($isCorporate ? 'enterprise' : 'personal'),
            'role'            => $isCorporate ? 'org_user' : 'user',
            'organization_id' => $organization?->id,
        ]);

        return [
            'user'  => $user->load('organization'),
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

        if ($user->two_factor_enabled) {
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
        $email        = $googleUser->getEmail();
        $isCorporate  = PublicEmailDetector::isCorporate($email);
        $organization = $isCorporate ? OrganizationResolver::fromEmail($email) : null;

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name'            => $googleUser->getName(),
                'email'           => $email,
                'organization_id' => $organization?->id,
                'account_type'    => $organization ? 'enterprise' : ($isCorporate ? 'enterprise' : 'personal'),
                'role'            => $isCorporate ? 'org_user' : 'user',
                'last_login_at'   => now(),
            ]
        );

        return [
            'user'               => $user->load('organization'),
            'token'              => $user->createToken('api')->plainTextToken,
            'requires_2fa_setup' => ! $user->two_factor_enabled,
        ];
    }

    private function syncEnterpriseOrganization(User $user): void
    {
        if ($user->isSysAdmin() || $user->organization_id) {
            return;
        }

        if ($user->isPersonalUser()) {
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
