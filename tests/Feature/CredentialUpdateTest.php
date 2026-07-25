<?php

use App\Models\Category;
use App\Models\Organization;
use App\Models\User;
use App\Services\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.credential_encryption_key', base64_encode(random_bytes(32)));
});

function updatableCredentialFixture(): array
{
    $organization = Organization::create(['name' => 'Cyberpass QA', 'slug' => 'cyberpass-update']);

    $user = User::factory()->create([
        'organization_id'         => $organization->id,
        'role'                    => 'org_admin',
        'account_type'            => 'enterprise',
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $category = Category::create(['organization_id' => $organization->id, 'name' => 'Infra']);

    $credential = app(CredentialService::class)->create($category, $organization, $user, [
        'name'     => 'VPN',
        'username' => 'vpn.user',
        'password' => 'super-secret',
        'url'      => 'https://vpn.example.com',
    ]);

    return compact('category', 'credential', 'user');
}

it('clears the username when it is sent explicitly as null', function () {
    ['category' => $category, 'credential' => $credential, 'user' => $user] = updatableCredentialFixture();

    Sanctum::actingAs($user);

    $this->putJson("/api/categories/{$category->id}/credentials/{$credential->id}", [
        'username' => null,
    ])->assertOk();

    expect($credential->fresh()->username)->toBeNull();
});

it('leaves the username untouched when the key is absent', function () {
    ['category' => $category, 'credential' => $credential, 'user' => $user] = updatableCredentialFixture();

    Sanctum::actingAs($user);

    $this->putJson("/api/categories/{$category->id}/credentials/{$credential->id}", [
        'name' => 'VPN corporativa',
    ])->assertOk();

    $fresh = $credential->fresh();

    expect($fresh->username)->toBe('vpn.user')
        ->and($fresh->name)->toBe('VPN corporativa');
});

it('re-encrypts the password and keeps the previous one in the version history', function () {
    ['category' => $category, 'credential' => $credential, 'user' => $user] = updatableCredentialFixture();

    Sanctum::actingAs($user);

    $originalCiphertext = $credential->encrypted_password;

    $this->putJson("/api/categories/{$category->id}/credentials/{$credential->id}", [
        'password' => 'nueva-secreta',
    ])->assertOk();

    $service = app(CredentialService::class);
    $fresh   = $credential->fresh();

    expect($fresh->encrypted_password)->not->toBe($originalCiphertext)
        ->and($service->decrypt($fresh))->toBe('nueva-secreta');

    $version = $fresh->versions()->latest('id')->first();

    expect($service->decryptVersion($version))->toBe('super-secret');
});
