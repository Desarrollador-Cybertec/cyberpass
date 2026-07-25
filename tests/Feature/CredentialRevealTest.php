<?php

use App\Models\Category;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;
use App\Services\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.credential_encryption_key', base64_encode(random_bytes(32)));
});

function createCredentialFixture(): array
{
    $organization = Organization::create([
        'name' => 'Cyberpass QA',
        'slug' => 'cyberpass-qa',
    ]);

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'role'                    => 'org_user',
        'account_type'            => 'enterprise',
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $category = Category::create([
        'organization_id' => $organization->id,
        'name' => 'Infra',
    ]);

    $credential = app(CredentialService::class)->create($category, $organization, $user, [
        'name'     => 'VPN',
        'username' => 'vpn.user',
        'password' => 'super-secret',
        'url'      => 'https://vpn.example.com',
        'type'     => 'password',
    ]);

    return compact('category', 'credential', 'organization', 'user');
}

it('logs reveal_password when revealing a credential', function () {
    ['category' => $category, 'credential' => $credential, 'user' => $user] = createCredentialFixture();

    Sanctum::actingAs($user);

    $this->getJson("/api/categories/{$category->id}/credentials/{$credential->id}/reveal")
        ->assertOk()
        ->assertJson([
            'password' => 'super-secret',
            'url'      => 'https://vpn.example.com',
        ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'organization_id' => $user->organization_id,
        'action' => 'reveal_password',
        'entity_type' => Credential::class,
        'entity_id' => $credential->id,
    ]);

    $this->assertDatabaseMissing('audit_logs', [
        'user_id' => $user->id,
        'action' => 'view',
        'entity_type' => Credential::class,
        'entity_id' => $credential->id,
    ]);
});

it('never returns the plaintext when showing a credential, and logs it as a view', function () {
    ['category' => $category, 'credential' => $credential, 'user' => $user] = createCredentialFixture();

    Sanctum::actingAs($user);

    $response = $this->getJson("/api/categories/{$category->id}/credentials/{$credential->id}")
        ->assertOk()
        ->assertJsonPath('url', 'https://vpn.example.com')
        ->assertJsonMissingPath('password');

    // El secreto no puede aparecer en NINGUNA parte del cuerpo, ni anidado.
    expect($response->getContent())->not->toContain('super-secret');

    // show() es una lectura de metadatos: se audita 'view', no 'reveal_password'.
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action' => 'view',
        'entity_type' => Credential::class,
        'entity_id' => $credential->id,
    ]);

    $this->assertDatabaseMissing('audit_logs', [
        'user_id' => $user->id,
        'action' => 'reveal_password',
        'entity_type' => Credential::class,
        'entity_id' => $credential->id,
    ]);
});

it('finds credentials by name with a case-insensitive search on any driver', function () {
    ['category' => $category, 'user' => $user] = createCredentialFixture();

    Sanctum::actingAs($user);

    // Cubre SearchOperator: con ILIKE literal esto reventaba en sqlite.
    $this->getJson("/api/categories/{$category->id}/credentials?q=vpn")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'VPN');

    $this->getJson("/api/categories/{$category->id}/credentials?q=nada-de-esto")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
