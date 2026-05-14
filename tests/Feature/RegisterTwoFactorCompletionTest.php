<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

it('creates the user only after the initial 2fa code is verified', function () {
    $email = 'pending.user@gmail.com';

    $registerResponse = $this->postJson('/api/auth/register', [
        'name'                  => 'Pending User',
        'email'                 => $email,
        'password'              => 'super-secret-123',
        'password_confirmation' => 'super-secret-123',
    ])
        ->assertStatus(202)
        ->assertJsonPath('requires_2fa_setup', true)
        ->assertJsonPath('user.email', $email)
        ->assertJsonMissingPath('token');

    $this->assertDatabaseMissing('users', [
        'email' => $email,
    ]);

    $registrationToken = $registerResponse->json('registration_token');

    $setupResponse = $this->postJson('/api/auth/2fa/setup', [
        'registration_token' => $registrationToken,
    ])
        ->assertOk()
        ->assertJsonPath('user.email', $email);

    $this->assertDatabaseMissing('users', [
        'email' => $email,
    ]);

    $otp = app(Google2FA::class)->getCurrentOtp($setupResponse->json('secret'));

    $this->postJson('/api/auth/2fa/enable', [
        'registration_token' => $registrationToken,
        'otp'                => $otp,
    ])
        ->assertStatus(201)
        ->assertJsonPath('user.email', $email)
        ->assertJsonPath('user.two_factor_enabled', true);

    $user = User::query()->where('email', $email)->first();

    expect($user)->not->toBeNull();
    expect($user->two_factor_enabled)->toBeTrue();
    expect($user->two_factor_confirmed_at)->not->toBeNull();
});

it('does not create the user when the initial 2fa code is invalid', function () {
    $email = 'invalid.otp@gmail.com';

    $registrationToken = $this->postJson('/api/auth/register', [
        'name'                  => 'Invalid Otp User',
        'email'                 => $email,
        'password'              => 'super-secret-123',
        'password_confirmation' => 'super-secret-123',
    ])
        ->assertStatus(202)
        ->json('registration_token');

    $this->postJson('/api/auth/2fa/setup', [
        'registration_token' => $registrationToken,
    ])->assertOk();

    $this->postJson('/api/auth/2fa/enable', [
        'registration_token' => $registrationToken,
        'otp'                => '000000',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);

    $this->assertDatabaseMissing('users', [
        'email' => $email,
    ]);
});

it('still enables 2fa for an authenticated existing user', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $setupResponse = $this->postJson('/api/auth/2fa/setup')
        ->assertOk();

    $otp = app(Google2FA::class)->getCurrentOtp($setupResponse->json('secret'));

    $this->postJson('/api/auth/2fa/enable', [
        'otp' => $otp,
    ])
        ->assertOk()
        ->assertJson([
            'message' => '2FA activado correctamente.',
        ]);

    $user->refresh();

    expect($user->two_factor_enabled)->toBeTrue();
    expect($user->two_factor_confirmed_at)->not->toBeNull();
});
