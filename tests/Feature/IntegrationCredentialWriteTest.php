<?php

use App\Helpers\IntegrationAbility;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Credential;
use App\Models\Organization;
use App\Services\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config()->set('app.credential_encryption_key', base64_encode(random_bytes(32)));
});

function orgActor(string $slug = 'cybertec-write'): array
{
    $organization = Organization::create(['name' => 'Cybertec', 'slug' => $slug]);

    $user = verifiedUser();
    $user->forceFill([
        'organization_id' => $organization->id,
        'role'            => 'org_admin',
        'account_type'    => 'enterprise',
    ])->save();

    $orgCategory = Category::create(['organization_id' => $organization->id, 'name' => 'Infra']);
    $vaultCategory = Category::create(['user_id' => $user->id, 'name' => 'Personal']);

    return compact('organization', 'user', 'orgCategory', 'vaultCategory');
}

it('creates an organization credential and never echoes the password', function () {
    ['user' => $user, 'orgCategory' => $category] = orgActor();

    $response = withBearer($this, integrationToken($user))
        ->postJson('/api/integration/credentials', [
            'scope'       => 'organization',
            'category_id' => $category->id,
            'name'        => 'VPN corporativa',
            'username'    => 'vpn.user',
            'password'    => 'Sup3r-Secreto!',
            'url'         => 'https://vpn.example.com',
        ])
        ->assertCreated()
        ->assertJsonPath('category.scope', 'organization')
        ->assertJsonMissingPath('password');

    expect($response->getContent())->not->toContain('Sup3r-Secreto!');

    $credential = Credential::find($response->json('id'));

    expect($credential->organization_id)->toBe($category->organization_id)
        ->and($credential->user_id)->toBeNull()
        ->and($credential->created_by)->toBe($user->id)
        ->and(app(CredentialService::class)->decrypt($credential))->toBe('Sup3r-Secreto!');
});

it('never leaves the plaintext in any table', function () {
    ['user' => $user, 'vaultCategory' => $category] = orgActor('cybertec-plaintext');

    $plain = 'Sup3r-Secreto-Unico-42!';

    withBearer($this, integrationToken($user))
        ->postJson('/api/integration/credentials', [
            'scope'       => 'personal',
            'category_id' => $category->id,
            'name'        => 'Correo',
            'password'    => $plain,
        ])->assertCreated();

    // Barrido agnóstico: atrapa cualquier regresión futura del tipo
    // "guardemos también una copia" en cualquier tabla nueva.
    foreach (DB::connection()->getSchemaBuilder()->getTableListing() as $table) {
        foreach (DB::table($table)->get() as $row) {
            expect(json_encode($row))->not->toContain($plain);
        }
    }
});

it('creates a personal vault credential with the right scope columns', function () {
    ['user' => $user, 'vaultCategory' => $category] = orgActor('cybertec-vault');

    $id = withBearer($this, integrationToken($user))
        ->postJson('/api/integration/credentials', [
            'scope'       => 'personal',
            'category_id' => $category->id,
            'name'        => 'Correo personal',
            'password'    => 'otra-secreta',
        ])
        ->assertCreated()
        ->assertJsonPath('category.scope', 'personal')
        ->json('id');

    $credential = Credential::find($id);

    // El CHECK de XOR no existe en sqlite, así que se afirman ambas columnas.
    expect($credential->user_id)->toBe($user->id)
        ->and($credential->organization_id)->toBeNull();
});

it('rejects a scope that does not match the category', function () {
    ['user' => $user, 'orgCategory' => $category] = orgActor('cybertec-mismatch');

    withBearer($this, integrationToken($user))
        ->postJson('/api/integration/credentials', [
            'scope'       => 'personal',   // la categoría es de organización
            'category_id' => $category->id,
            'name'        => 'Fuga',
            'password'    => 'secreto',
        ])->assertStatus(422);

    expect(Credential::count())->toBe(0);
});

it('returns 404 for a category belonging to someone else', function () {
    ['user' => $user] = orgActor('cybertec-a');
    ['vaultCategory' => $foreignCategory] = orgActor('cybertec-b');

    withBearer($this, integrationToken($user))
        ->postJson('/api/integration/credentials', [
            'scope'       => 'personal',
            'category_id' => $foreignCategory->id,
            'name'        => 'Ajena',
            'password'    => 'secreto',
        ])->assertNotFound();

    expect(Credential::count())->toBe(0);
});

it('reveals only credentials the token owner created', function () {
    ['organization' => $organization, 'user' => $owner, 'orgCategory' => $category] = orgActor('cybertec-reveal');

    $colleague = verifiedUser();
    $colleague->forceFill([
        'organization_id' => $organization->id,
        'role'            => 'org_user',
        'account_type'    => 'enterprise',
    ])->save();

    $theirs = app(CredentialService::class)->create($category, $organization, $colleague, [
        'name' => 'De otra persona', 'password' => 'no-deberia-verse',
    ]);

    $mine = app(CredentialService::class)->create($category, $organization, $owner, [
        'name' => 'Mia', 'password' => 'si-se-ve',
    ]);

    $token = integrationToken($owner);

    withBearer($this, $token)
        ->getJson("/api/integration/credentials/{$mine->id}/reveal")
        ->assertOk()
        ->assertJsonPath('password', 'si-se-ve')
        ->assertHeader('Cache-Control', 'no-store, private');

    // CredentialPolicy::view() dejaria a cualquier miembro de la organizacion
    // ver esta credencial; la integracion es deliberadamente mas estricta.
    withBearer($this, $token)
        ->getJson("/api/integration/credentials/{$theirs->id}/reveal")
        ->assertNotFound();
});

it('records the provenance of every integration write', function () {
    ['user' => $user, 'vaultCategory' => $category] = orgActor('cybertec-audit');

    $id = withBearer($this, integrationToken($user))
        ->withHeader('X-Integration-Client', 'axis')
        ->postJson('/api/integration/credentials', [
            'scope'       => 'personal',
            'category_id' => $category->id,
            'name'        => 'Auditada',
            'password'    => 'secreto',
        ])->assertCreated()->json('id');

    $log = AuditLog::where('entity_type', Credential::class)->where('entity_id', $id)->latest('id')->first();

    expect($log->metadata['source'])->toBe('integration')
        ->and($log->metadata['client'])->toBe('axis')
        ->and($log->metadata['scope'])->toBe('personal')
        ->and($log->metadata['token_id'])->not->toBeNull();
});

it('ignores a forged client header', function () {
    ['user' => $user, 'vaultCategory' => $category] = orgActor('cybertec-forge');

    $id = withBearer($this, integrationToken($user))
        ->withHeader('X-Integration-Client', 'malicioso')
        ->postJson('/api/integration/credentials', [
            'scope'       => 'personal',
            'category_id' => $category->id,
            'name'        => 'Falsificada',
            'password'    => 'secreto',
        ])->assertCreated()->json('id');

    $log = AuditLog::where('entity_type', Credential::class)->where('entity_id', $id)->latest('id')->first();

    expect($log->metadata['client'])->toBeNull()
        ->and($log->metadata['source'])->toBe('integration');
});

it('rejects an invalid url before sending anything', function () {
    ['user' => $user, 'vaultCategory' => $category] = orgActor('cybertec-url');

    withBearer($this, integrationToken($user))
        ->postJson('/api/integration/credentials', [
            'scope'       => 'personal',
            'category_id' => $category->id,
            'name'        => 'Sin esquema',
            'password'    => 'secreto',
            'url'         => 'www.ejemplo.com',
        ])->assertStatus(422);

    expect(Credential::count())->toBe(0);
});

it('updates a credential and can clear its username', function () {
    ['user' => $user, 'vaultCategory' => $category] = orgActor('cybertec-update');

    $id = withBearer($this, integrationToken($user))
        ->postJson('/api/integration/credentials', [
            'scope'       => 'personal',
            'category_id' => $category->id,
            'name'        => 'Con usuario',
            'username'    => 'alguien',
            'password'    => 'secreto',
        ])->assertCreated()->json('id');

    withBearer($this, integrationToken($user, IntegrationAbility::all()))
        ->putJson("/api/integration/credentials/{$id}", ['username' => null])
        ->assertOk()
        ->assertJsonPath('username', null);

    expect(Credential::find($id)->username)->toBeNull();
});
