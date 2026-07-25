<?php

use App\Helpers\IntegrationAbility;
use App\Models\AuditLog;
use App\Models\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function humanSession(object $test): array
{
    $user = verifiedUser(['password' => 'super-secret-123']);
    $user->forceFill(['role' => 'user', 'account_type' => 'personal'])->save();

    $temp = $test->postJson('/api/auth/login', [
        'email' => $user->email, 'password' => 'super-secret-123',
    ])->assertOk()->json('temp_token');

    $token = $test->postJson('/api/auth/login/2fa', [
        'temp_token' => $temp,
        'otp'        => app(Google2FA::class)->getCurrentOtp($user->two_factor_secret),
    ])->assertOk()->json('token');

    return [$user, $token];
}

function currentOtp(App\Models\User $user): string
{
    return app(Google2FA::class)->getCurrentOtp($user->two_factor_secret);
}

it('issues a token whose plaintext is returned exactly once', function () {
    [$user, $session] = humanSession($this);

    $response = withBearer($this, $session)->postJson('/api/auth/integration-tokens', [
        'name' => 'Axis producción',
        'otp'  => currentOtp($user),
    ])->assertCreated();

    $plain = $response->json('token');

    expect($plain)->toBeString()->not->toBeEmpty()
        ->and($response->json('abilities'))->toBe(IntegrationAbility::defaults());

    $stored = PersonalAccessToken::findToken($plain);

    expect($stored->type)->toBe(PersonalAccessToken::TYPE_INTEGRATION)
        ->and($stored->expires_at->isAfter(now()->addDays(360)))->toBeTrue();

    // El listado nunca vuelve a exponerlo.
    $list = withBearer($this, $session)->getJson('/api/auth/integration-tokens')->assertOk();

    expect($list->getContent())->not->toContain($plain);
    expect($list->json('data.0'))->not->toHaveKey('token');
});

it('refuses to issue without a valid otp', function () {
    [$user, $session] = humanSession($this);

    withBearer($this, $session)->postJson('/api/auth/integration-tokens', [
        'name' => 'Axis',
        'otp'  => '000000',
    ])->assertStatus(422);

    expect($user->tokens()->integrations()->count())->toBe(0);

    // Un intento fallido queda auditado.
    expect(AuditLog::where('action', '2fa_failed')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('refuses abilities outside the whitelist, including the wildcard', function () {
    [$user, $session] = humanSession($this);

    withBearer($this, $session)->postJson('/api/auth/integration-tokens', [
        'name'      => 'Axis',
        'abilities' => ['*'],
        'otp'       => currentOtp($user),
    ])->assertStatus(422);

    withBearer($this, $session)->postJson('/api/auth/integration-tokens', [
        'name'      => 'Axis',
        'abilities' => ['integration:credentials.inventar'],
        'otp'       => currentOtp($user),
    ])->assertStatus(422);

    expect($user->tokens()->integrations()->count())->toBe(0);
});

it('caps the requested lifetime', function () {
    [$user, $session] = humanSession($this);

    withBearer($this, $session)->postJson('/api/auth/integration-tokens', [
        'name'            => 'Axis',
        'expires_in_days' => 5000,
        'otp'             => currentOtp($user),
    ])->assertStatus(422);
});

it('enforces the per-user limit of active tokens', function () {
    [$user, $session] = humanSession($this);

    config()->set('integrations.max_tokens_per_user', 2);

    foreach (['uno', 'dos'] as $name) {
        withBearer($this, $session)->postJson('/api/auth/integration-tokens', [
            'name' => $name, 'otp' => currentOtp($user),
        ])->assertCreated();
    }

    withBearer($this, $session)->postJson('/api/auth/integration-tokens', [
        'name' => 'tres', 'otp' => currentOtp($user),
    ])->assertStatus(422);
});

it('revokes one token and audits it', function () {
    [$user, $session] = humanSession($this);

    $plain = withBearer($this, $session)->postJson('/api/auth/integration-tokens', [
        'name' => 'Axis', 'otp' => currentOtp($user),
    ])->assertCreated()->json('token');

    $id = PersonalAccessToken::findToken($plain)->id;

    withBearer($this, $session)->deleteJson("/api/auth/integration-tokens/{$id}")->assertOk();

    expect(PersonalAccessToken::find($id))->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action'      => 'delete',
        'entity_type' => PersonalAccessToken::class,
        'entity_id'   => $id,
    ]);
});

it('cannot revoke another user\'s token', function () {
    [, $session] = humanSession($this);

    $victim = verifiedUser();
    $victimToken = PersonalAccessToken::findToken(integrationToken($victim));

    withBearer($this, $session)
        ->deleteJson("/api/auth/integration-tokens/{$victimToken->id}")
        ->assertNotFound();

    expect(PersonalAccessToken::find($victimToken->id))->not->toBeNull();
});

it('cannot revoke a session token through this route', function () {
    [$user, $session] = humanSession($this);

    $sessionTokenId = $user->tokens()->sessions()->first()->id;

    withBearer($this, $session)
        ->deleteJson("/api/auth/integration-tokens/{$sessionTokenId}")
        ->assertNotFound();

    expect(PersonalAccessToken::find($sessionTokenId))->not->toBeNull();
});

it('revokes every integration token at once without touching the session', function () {
    [$user, $session] = humanSession($this);

    foreach (['uno', 'dos'] as $name) {
        withBearer($this, $session)->postJson('/api/auth/integration-tokens', [
            'name' => $name, 'otp' => currentOtp($user),
        ])->assertCreated();
    }

    withBearer($this, $session)->deleteJson('/api/auth/integration-tokens')
        ->assertOk()
        ->assertJsonPath('revoked', 2);

    expect($user->tokens()->integrations()->count())->toBe(0)
        ->and($user->tokens()->sessions()->count())->toBe(1);
});

it('cannot be used by an integration token to mint another one', function () {
    $user = verifiedUser();
    $token = integrationToken($user, IntegrationAbility::all());

    withBearer($this, $token)->postJson('/api/auth/integration-tokens', [
        'name' => 'escalada', 'otp' => currentOtp($user),
    ])->assertForbidden()->assertJsonPath('error_code', 'session_token_required');
});
