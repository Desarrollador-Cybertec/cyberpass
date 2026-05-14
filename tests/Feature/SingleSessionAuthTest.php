<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

it('returns an active-session response instead of issuing a new token when the same user logs in again', function () {
    $user = User::factory()->create([
        'email'    => 'single-session@gmail.com',
        'password' => 'super-secret-123',
    ]);

    $user->forceFill([
        'role'         => 'user',
        'account_type' => 'personal',
    ])->save();

    $firstToken = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'super-secret-123',
    ])
        ->assertOk()
        ->json('token');

    $this->withToken($firstToken)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email);

    $secondResponse = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'super-secret-123',
    ])
        ->assertStatus(409)
        ->assertJsonPath('session_active', true)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('message', 'Ya existe una sesión activa para este usuario.');

    expect($user->fresh()->tokens()->count())->toBe(1);
    expect(PersonalAccessToken::findToken($firstToken))->not->toBeNull();
    expect($secondResponse->json('session_expires_at'))->not->toBeNull();

    $activeSessionAudit = AuditLog::query()
        ->where('user_id', $user->id)
        ->where('action', 'login_failed')
        ->latest('id')
        ->first();

    expect($activeSessionAudit)->not->toBeNull();
    expect($activeSessionAudit->metadata['reason'])->toBe('active_session');

    $this->withToken($firstToken)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

it('returns an active-session response before requesting otp when a 2fa user is already logged in', function () {
    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->create([
        'email'    => 'single-session-2fa@gmail.com',
        'password' => 'super-secret-123',
    ]);

    $user->forceFill([
        'role'                    => 'user',
        'account_type'            => 'personal',
        'two_factor_secret'       => $secret,
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
    ])->save();

    $firstTempToken = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'super-secret-123',
    ])
        ->assertOk()
        ->assertJsonPath('requires_2fa', true)
        ->json('temp_token');

    $firstToken = $this->postJson('/api/auth/login/2fa', [
        'temp_token' => $firstTempToken,
        'otp'        => app(Google2FA::class)->getCurrentOtp($secret),
    ])
        ->assertOk()
        ->json('token');

    $secondResponse = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'super-secret-123',
    ])
        ->assertStatus(409)
        ->assertJsonPath('session_active', true)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('message', 'Ya existe una sesión activa para este usuario.');

    expect($user->fresh()->tokens()->count())->toBe(1);
    expect(PersonalAccessToken::findToken($firstToken))->not->toBeNull();
    expect($secondResponse->json('temp_token'))->toBeNull();
    expect($secondResponse->json('session_expires_at'))->not->toBeNull();

    $this->withToken($firstToken)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

it('keeps the existing session active when an old 2fa challenge is completed after another login succeeded', function () {
    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->create([
        'email'    => 'single-session-race@gmail.com',
        'password' => 'super-secret-123',
    ]);

    $user->forceFill([
        'role'                    => 'user',
        'account_type'            => 'personal',
        'two_factor_secret'       => $secret,
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
    ])->save();

    $firstTempToken = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'super-secret-123',
    ])
        ->assertOk()
        ->assertJsonPath('requires_2fa', true)
        ->json('temp_token');

    $secondTempToken = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'super-secret-123',
    ])
        ->assertOk()
        ->assertJsonPath('requires_2fa', true)
        ->json('temp_token');

    $activeToken = $this->postJson('/api/auth/login/2fa', [
        'temp_token' => $secondTempToken,
        'otp'        => app(Google2FA::class)->getCurrentOtp($secret),
    ])
        ->assertOk()
        ->json('token');

    $this->postJson('/api/auth/login/2fa', [
        'temp_token' => $firstTempToken,
        'otp'        => app(Google2FA::class)->getCurrentOtp($secret),
    ])
        ->assertStatus(409)
        ->assertJsonPath('session_active', true)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('message', 'Ya existe una sesión activa para este usuario.');

    expect($user->fresh()->tokens()->count())->toBe(1);
    expect(PersonalAccessToken::findToken($activeToken))->not->toBeNull();

    $this->withToken($activeToken)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

it('expires auth tokens five minutes after issuance', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

    try {
        $user = User::factory()->create([
            'email'    => 'token-expiration@gmail.com',
            'password' => 'super-secret-123',
        ]);

        $user->forceFill([
            'role'         => 'user',
            'account_type' => 'personal',
        ])->save();

        $issuedAt = now()->copy();

        $token = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'super-secret-123',
        ])
            ->assertOk()
            ->json('token');

        $storedToken = PersonalAccessToken::findToken($token);

        expect($storedToken)->not->toBeNull();
        expect($storedToken->expires_at?->equalTo($issuedAt->copy()->addMinutes(5)))->toBeTrue();

        Carbon::setTestNow($issuedAt->copy()->addMinutes(6));

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    } finally {
        Carbon::setTestNow();
    }
});
