<?php

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

it('invalidates the previous token when the same user logs in again', function () {
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

    $secondToken = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'super-secret-123',
    ])
        ->assertOk()
        ->json('token');

    expect($secondToken)->not->toBe($firstToken);
    expect($user->fresh()->tokens()->count())->toBe(1);
    expect(PersonalAccessToken::findToken($firstToken))->toBeNull();
    expect(PersonalAccessToken::findToken($secondToken))->not->toBeNull();

    $this->withToken($firstToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    $this->withToken($secondToken)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

it('keeps only the latest token after completing 2fa login again', function () {
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

    $secondTempToken = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'super-secret-123',
    ])
        ->assertOk()
        ->assertJsonPath('requires_2fa', true)
        ->json('temp_token');

    $secondToken = $this->postJson('/api/auth/login/2fa', [
        'temp_token' => $secondTempToken,
        'otp'        => app(Google2FA::class)->getCurrentOtp($secret),
    ])
        ->assertOk()
        ->json('token');

    expect($secondToken)->not->toBe($firstToken);
    expect($user->fresh()->tokens()->count())->toBe(1);
    expect(PersonalAccessToken::findToken($firstToken))->toBeNull();
    expect(PersonalAccessToken::findToken($secondToken))->not->toBeNull();

    $this->withToken($firstToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    $this->withToken($secondToken)
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
