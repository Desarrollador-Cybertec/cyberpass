<?php

use App\Models\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use PragmaRX\Google2FA\Google2FA;

/**
 * El archivo que sostiene todo el mecanismo de tokens de integración.
 *
 * Recordatorio: aquí NO se puede usar Sanctum::actingAs(). Monta un mock que no
 * pasa por el Guard, así que el callback authenticateAccessTokensUsing() nunca
 * corre y el test pasaría sin comprobar nada. Siempre ->withToken($plain).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config()->set('app.credential_encryption_key', base64_encode(random_bytes(32)));
});

function loginFully(object $test, App\Models\User $user, string $password = 'super-secret-123'): string
{
    $tempToken = $test->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => $password,
    ])->assertOk()->json('temp_token');

    return $test->postJson('/api/auth/login/2fa', [
        'temp_token' => $tempToken,
        'otp'        => app(Google2FA::class)->getCurrentOtp($user->two_factor_secret),
    ])->assertOk()->json('token');
}

it('survives a full human login with 2fa', function () {
    $user = verifiedUser(['password' => 'super-secret-123', 'role' => 'user', 'account_type' => 'personal']);

    $integration = integrationToken($user);
    $integrationId = PersonalAccessToken::findToken($integration)->id;

    loginFully($this, $user);

    withBearer($this, $integration)->getJson('/api/integration/me')->assertOk();

    expect($user->tokens()->integrations()->count())->toBe(1)
        ->and($user->tokens()->sessions()->count())->toBe(1)
        // El login no borro la fila: es el MISMO token, no uno nuevo.
        ->and(PersonalAccessToken::findToken($integration)?->id)->toBe($integrationId);
});

it('does not count as an active session, so the user can still log in', function () {
    $user = verifiedUser(['password' => 'super-secret-123', 'role' => 'user', 'account_type' => 'personal']);

    integrationToken($user);

    // Sin el filtro ->sessions() en findActiveToken(), esto responderia 409
    // session_active para siempre y el usuario quedaria fuera de Cyberpass.
    $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'super-secret-123',
    ])->assertOk()->assertJsonPath('requires_2fa', true);
});

it('outlives the global sanctum expiration cap while session tokens do not', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-25 10:00:00'));

    try {
        $user = verifiedUser(['password' => 'super-secret-123', 'role' => 'user', 'account_type' => 'personal']);

        $integration = integrationToken($user);
        $session = loginFully($this, $user);

        // config('sanctum.expiration') son 5 minutos en la suite.
        Carbon::setTestNow(Carbon::parse('2026-07-25 12:00:00'));

        withBearer($this, $session)->getJson('/api/auth/me')->assertUnauthorized();
        withBearer($this, $integration)->getJson('/api/integration/me')->assertOk();
    } finally {
        Carbon::setTestNow();
    }
});

it('honours its own expires_at', function () {
    $user = verifiedUser(['role' => 'user', 'account_type' => 'personal']);

    $expired = integrationToken($user, null, now()->subDay());

    $this->withToken($expired)->getJson('/api/integration/me')->assertUnauthorized();
});

it('stops working the moment the row is deleted', function () {
    $user = verifiedUser(['role' => 'user', 'account_type' => 'personal']);

    $token = integrationToken($user);
    withBearer($this, $token)->getJson('/api/integration/me')->assertOk();

    PersonalAccessToken::findToken($token)->delete();

    // withBearer() olvida el guard; sin eso el usuario ya resuelto seguiria
    // autenticado y este assert pasaria con la fila del token ya borrada.
    withBearer($this, $token)->getJson('/api/integration/me')->assertUnauthorized();
});

it('survives a password change that kills the other sessions', function () {
    $user = verifiedUser(['password' => 'super-secret-123', 'role' => 'user', 'account_type' => 'personal']);

    $integration = integrationToken($user);
    $session = loginFully($this, $user);

    withBearer($this, $session)->postJson('/api/auth/change-password', [
        'otp'                   => app(Google2FA::class)->getCurrentOtp($user->two_factor_secret),
        'password'              => 'otra-clave-larga-123',
        'password_confirmation' => 'otra-clave-larga-123',
    ])->assertOk();

    withBearer($this, $integration)->getJson('/api/integration/me')->assertOk();

    expect($user->tokens()->integrations()->count())->toBe(1);
});
