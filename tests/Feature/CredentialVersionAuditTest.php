<?php

use App\Models\Category;
use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.credential_encryption_key', base64_encode(random_bytes(32)));
});

function createVersionAuditFixture(): array
{
    $organization = Organization::create([
        'name' => 'Cyberpass Audit QA',
        'slug' => 'cyberpass-audit-qa',
    ]);

    $category = Category::create([
        'organization_id' => $organization->id,
        'name' => 'Infra',
    ]);

    $creator = User::factory()->create([
        'name' => 'SysAdmin A',
        'role' => 'sysadmin',
        'account_type' => 'sysadmin',
    ]);

    $editor = User::factory()->create([
        'name' => 'SysAdmin B',
        'role' => 'sysadmin',
        'account_type' => 'sysadmin',
    ]);

    $restorer = User::factory()->create([
        'name' => 'SysAdmin C',
        'role' => 'sysadmin',
        'account_type' => 'sysadmin',
    ]);

    $credential = app(CredentialService::class)->create($category, $organization, $creator, [
        'name' => 'VPN',
        'username' => 'vpn.user',
        'password' => 'initial-secret',
        'notes' => 'otp backup code',
        'type' => 'password',
    ]);

    return compact('category', 'credential', 'creator', 'editor', 'restorer');
}

it('records the authenticated sysadmin as version actor during updates', function () {
    ['category' => $category, 'credential' => $credential, 'creator' => $creator, 'editor' => $editor] = createVersionAuditFixture();

    Sanctum::actingAs($editor);

    $this->putJson("/api/categories/{$category->id}/credentials/{$credential->id}", [
        'password' => 'rotated-secret',
        'changed_by' => $creator->id,
    ])->assertOk();

    $version = CredentialVersion::query()
        ->where('credential_id', $credential->id)
        ->latest('id')
        ->firstOrFail();

    expect($version->changed_by)->toBe($editor->id)
        ->and($version->encrypted_password)->not->toBe('initial-secret');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $editor->id,
        'action' => 'update',
        'entity_type' => Credential::class,
        'entity_id' => $credential->id,
    ]);
});

it('tracks distinct sysadmins across restore and keeps version history free of plaintext passwords', function () {
    ['category' => $category, 'credential' => $credential, 'editor' => $editor, 'restorer' => $restorer] = createVersionAuditFixture();

    Sanctum::actingAs($editor);

    $this->putJson("/api/categories/{$category->id}/credentials/{$credential->id}", [
        'password' => 'rotated-secret',
        'username' => 'vpn.rotated',
    ])->assertOk();

    $versionToRestore = CredentialVersion::query()
        ->where('credential_id', $credential->id)
        ->latest('id')
        ->firstOrFail();

    Sanctum::actingAs($restorer);

    $this->postJson("/api/categories/{$category->id}/credentials/{$credential->id}/versions/{$versionToRestore->id}/restore")
        ->assertOk()
        ->assertJsonPath('message', 'Versión restaurada correctamente.');

    $latestVersion = CredentialVersion::query()
        ->where('credential_id', $credential->id)
        ->latest('id')
        ->firstOrFail();

    expect($latestVersion->changed_by)->toBe($restorer->id);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $restorer->id,
        'action' => 'update',
        'entity_type' => Credential::class,
        'entity_id' => $credential->id,
    ]);

    $this->getJson("/api/categories/{$category->id}/credentials/{$credential->id}/reveal")
        ->assertOk()
        ->assertJson([
            'password' => 'initial-secret',
            'notes' => 'otp backup code',
        ]);

    $historyResponse = $this->getJson("/api/categories/{$category->id}/credentials/{$credential->id}/versions")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'username',
                    'changed_by' => ['id', 'name'],
                    'created_at',
                ],
            ],
        ])
        ->assertJsonPath('data.0.changed_by.id', $restorer->id)
        ->assertJsonPath('data.0.changed_by.name', $restorer->name)
        ->assertJsonPath('data.1.changed_by.id', $editor->id)
        ->assertJsonPath('data.1.changed_by.name', $editor->name);

    expect($historyResponse->getContent())
        ->not->toContain('initial-secret')
        ->not->toContain('rotated-secret');
});