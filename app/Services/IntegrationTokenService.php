<?php

namespace App\Services;

use App\Helpers\IntegrationAbility;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class IntegrationTokenService
{
    public function __construct(
        private Google2FA $google2fa,
        private AuditService $audit,
    ) {}

    /**
     * @return array{model: PersonalAccessToken, plain_text: string}
     */
    public function issue(User $user, array $data): array
    {
        $this->assertOtp($user, $data['otp']);

        $max = (int) config('integrations.max_tokens_per_user', 10);

        abort_if(
            $user->tokens()->integrations()->count() >= $max,
            422,
            'Alcanzaste el máximo de tokens de integración activos. Revoca alguno antes de crear otro.',
        );

        $days = (int) ($data['expires_in_days'] ?? config('integrations.token_default_days', 365));
        $expiresAt = now()->addDays($days);
        $abilities = $data['abilities'] ?? IntegrationAbility::defaults();

        $new = $user->createToken($data['name'], $abilities, $expiresAt);

        // createToken() no rellena 'type': no está en el $fillable de Sanctum.
        $new->accessToken->forceFill(['type' => PersonalAccessToken::TYPE_INTEGRATION])->save();

        $this->audit->log($user, 'create', PersonalAccessToken::class, $new->accessToken->id, [
            'source'     => 'integration',
            'kind'       => 'integration_token',
            'token_name' => $data['name'],
            'abilities'  => $abilities,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return ['model' => $new->accessToken, 'plain_text' => $new->plainTextToken];
    }

    public function revoke(User $user, PersonalAccessToken $token): void
    {
        // 404 y no 403: no confirmamos la existencia de tokens ajenos.
        abort_if($token->tokenable_id !== $user->id || $token->tokenable_type !== User::class, 404);

        // Nadie borra su token de SESIÓN por esta vía: para eso está /logout.
        abort_unless($token->isIntegration(), 404);

        $id = $token->id;
        $name = $token->name;

        $token->delete();

        $this->audit->log($user, 'delete', PersonalAccessToken::class, $id, [
            'source'     => 'integration',
            'kind'       => 'integration_token',
            'token_name' => $name,
        ]);
    }

    /** Botón de pánico: revoca todas las integraciones del usuario de una vez. */
    public function revokeAll(User $user): int
    {
        $count = $user->tokens()->integrations()->count();

        $user->tokens()->integrations()->delete();

        if ($count > 0) {
            $this->audit->log($user, 'delete', PersonalAccessToken::class, null, [
                'source'  => 'integration',
                'kind'    => 'integration_token',
                'revoked' => $count,
                'bulk'    => true,
            ]);
        }

        return $count;
    }

    private function assertOtp(User $user, string $otp): void
    {
        if (! $this->google2fa->verifyKey((string) $user->two_factor_secret, $otp)) {
            $this->audit->log($user, '2fa_failed', null, null, [
                'reason'  => 'invalid_otp',
                'context' => 'integration_token',
            ]);

            throw ValidationException::withMessages(['otp' => ['Código OTP incorrecto.']]);
        }
    }
}
