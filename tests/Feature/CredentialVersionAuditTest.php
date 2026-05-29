<?php

use App\Models\AuditLog;
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

function createSysAdmin(string $name): User
{
    return User::factory()->create([
        'name'                    => $name,
        'role'                    => 'sysadmin',
        'account_type'            => 'sysadmin',
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
    ]);
}

it('attributes credential version history to the authenticated sysadmin actors', function () {
    $organization = Organization::create([
        'name' => 'Cyberpass Audit Org',
        'slug' => 'cyberpass-audit-org',
    ]);

    $category = Category::create([
        'organization_id' => $organization->id,
        'name'            => 'Production Secrets',
    ]);

    $creator = createSysAdmin('SysAdmin A');
    $editor = createSysAdmin('SysAdmin B');
    $restorer = createSysAdmin('SysAdmin C');

    $credential = app(CredentialService::class)->create($category, $organization, $creator, [
        'name'     => 'VPN Gateway',
        'username' => 'vpn.admin',
        'password' => 'initial-secret',
        'url'      => 'https://vpn.example.com',
        'type'     => 'password',
    ]);

    Sanctum::actingAs($editor);

    $this->putJson("/api/categories/{$category->id}/credentials/{$credential->id}", [
        'username' => 'vpn.editor',
        'password' => 'updated-secret',
        'url'      => 'https://vpn-updated.example.com',
    ])->assertOk();

    $credential->refresh();

    $updatedVersion = $credential->versions()->latest('id')->firstOrFail();

    expect($updatedVersion->changed_by)->toBe($editor->id);
    expect($updatedVersion->username)->toBe('vpn.admin');
    expect($updatedVersion->encrypted_password)->not->toBe('initial-secret');
    expect($updatedVersion->encrypted_password)->not->toBe('updated-secret');

    $this->assertDatabaseHas('audit_logs', [
        'user_id'         => $editor->id,
        'action'          => 'update',
        'entity_type'     => Credential::class,
        'entity_id'       => $credential->id,
        'organization_id' => null,
    ]);

    Sanctum::actingAs($restorer);

    $this->postJson("/api/categories/{$category->id}/credentials/{$credential->id}/versions/{$updatedVersion->id}/restore")
        ->assertOk()
        ->assertJsonPath('message', 'Versión restaurada correctamente.');

    $credential->refresh();

    $restoredVersion = $credential->versions()
        ->where('changed_by', $restorer->id)
        ->latest('id')
        ->firstOrFail();

    expect($restoredVersion->username)->toBe('vpn.editor');
    expect($restoredVersion->encrypted_password)->not->toBe('initial-secret');
    expect($restoredVersion->encrypted_password)->not->toBe('updated-secret');

    $restoreAudit = AuditLog::query()
        ->where('user_id', $restorer->id)
        ->where('action', 'update')
        ->where('entity_type', Credential::class)
        ->where('entity_id', $credential->id)
        ->latest('id')
        ->firstOrFail();

    expect($restoreAudit->metadata['restored_version_id'] ?? null)->toBe($updatedVersion->id);
    expect(app(CredentialService::class)->decrypt($credential))->toBe('initial-secret');

    $versionsResponse = $this->getJson("/api/categories/{$category->id}/credentials/{$credential->id}/versions")
        ->assertOk();

    $versions = collect($versionsResponse->json('data'));

    expect($versions)->toHaveCount(2);

    $versionActors = $versions->mapWithKeys(fn (array $version) => [
        $version['changed_by']['id'] => $version['changed_by']['name'],
    ]);

    expect($versionActors->all())->toMatchArray([
        $editor->id => $editor->name,
        $restorer->id => $restorer->name,
    ]);

    foreach ($versions as $versionPayload) {
        $this->assertArrayNotHasKey('password', $versionPayload);
        $this->assertArrayNotHasKey('encrypted_password', $versionPayload);
        $this->assertArrayNotHasKey('iv', $versionPayload);
    }

    expect(json_encode($versions->all(), JSON_THROW_ON_ERROR))
        ->not->toContain('initial-secret')
        ->not->toContain('updated-secret');
});
