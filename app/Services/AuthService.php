<?php

namespace App\Services;

use App\Helpers\OrganizationResolver;
use App\Helpers\PendingRegistrationStore;
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
        $email = $data['email'];
        ['organization' => $organization, 'is_corporate' => $isCorporate] = $this->resolveRegistrationContext($email);

        $registrationToken = PendingRegistrationStore::store([
            'name'            => $data['name'],
            'email'           => $email,
            'password'        => Hash::make($data['password']),
            'account_type'    => $isCorporate ? 'enterprise' : 'personal',
            'role'            => $isCorporate ? 'org_user' : 'user',
            'organization_id' => $organization?->id,
            'two_factor_secret' => null,
        ]);

        return [
            'registration_token' => $registrationToken,
            'pending_user'       => $this->pendingRegistrationProfile([
                'name'            => $data['name'],
                'email'           => $email,
                'account_type'    => $isCorporate ? 'enterprise' : 'personal',
                'role'            => $isCorporate ? 'org_user' : 'user',
                'organization_id' => $organization?->id,
            ]),
        ];
    }

    public function getPendingRegistration(string $registrationToken): array
    {
        $pendingRegistration = PendingRegistrationStore::retrieve($registrationToken);

        if (! is_array($pendingRegistration)) {
            throw ValidationException::withMessages([
                'registration_token' => ['Registro pendiente inválido o expirado.'],
            ]);
        }

        return $pendingRegistration;
    }

    public function rememberPendingRegistrationSecret(string $registrationToken, string $secret): array
    {
        $pendingRegistration = $this->getPendingRegistration($registrationToken);

        PendingRegistrationStore::update($registrationToken, [
            'two_factor_secret' => $secret,
        ]);

        return array_merge($pendingRegistration, [
            'two_factor_secret' => $secret,
        ]);
    }

    public function completePendingRegistration(string $registrationToken): array
    {
        $pendingRegistration = $this->getPendingRegistration($registrationToken);

        if (empty($pendingRegistration['two_factor_secret'])) {
            throw ValidationException::withMessages([
                'registration_token' => ['Debes completar el setup de 2FA antes de activar la cuenta.'],
            ]);
        }

        if (User::where('email', $pendingRegistration['email'])->exists()) {
            PendingRegistrationStore::forget($registrationToken);

            throw ValidationException::withMessages([
                'email' => ['Ya existe una cuenta registrada con este correo.'],
            ]);
        }

        $user = new User;
        $user->forceFill([
            'name'                    => $pendingRegistration['name'],
            'email'                   => $pendingRegistration['email'],
            'password'                => $pendingRegistration['password'],
            'account_type'            => $pendingRegistration['account_type'],
            'role'                    => $pendingRegistration['role'],
            'organization_id'         => $pendingRegistration['organization_id'],
            'two_factor_secret'       => $pendingRegistration['two_factor_secret'],
            'two_factor_enabled'      => true,
            'two_factor_confirmed_at' => now(),
            'last_login_at'           => now(),
        ])->save();

        PendingRegistrationStore::forget($registrationToken);

        return [
            'user'  => $user->load('organization'),
            'token' => $this->issueSingleSessionToken($user),
        ];
    }

    public function pendingRegistrationProfile(array $pendingRegistration): array
    {
        $organization = null;

        if (! empty($pendingRegistration['organization_id'])) {
            $organization = [
                'id' => $pendingRegistration['organization_id'],
            ];
        }

        return [
            'name'         => $pendingRegistration['name'],
            'email'        => $pendingRegistration['email'],
            'role'         => $pendingRegistration['role'],
            'account_type' => $pendingRegistration['account_type'],
            'organization' => $organization,
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
            'token' => $this->issueSingleSessionToken($user),
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
            'token' => $this->issueSingleSessionToken($user),
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
            'token'              => $this->issueSingleSessionToken($user),
            'requires_2fa_setup' => ! $user->two_factor_enabled,
        ];
    }

    private function issueSingleSessionToken(User $user): string
    {
        $user->tokens()->delete();

        return $user->createToken('api')->plainTextToken;
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

    private function resolveRegistrationContext(string $email): array
    {
        $isCorporate = PublicEmailDetector::isCorporate($email);

        return [
            'is_corporate' => $isCorporate,
            'organization' => $isCorporate ? OrganizationResolver::fromEmail($email) : null,
        ];
    }
}
