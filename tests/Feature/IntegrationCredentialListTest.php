<?php

use App\Helpers\IntegrationAbility;
use App\Models\Category;
use App\Models\Organization;
use App\Services\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config()->set('app.credential_encryption_key', base64_encode(random_bytes(32)));
});

/** Escenario base: una organizacion, su categoria y la boveda personal. */
function listActor(string $slug = 'cybertec-list'): array
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

function makeOrgCredential(array $ctx, array $attributes, $creator = null)
{
    return app(CredentialService::class)->create(
        $ctx['orgCategory'],
        $ctx['organization'],
        $creator ?? $ctx['user'],
        array_merge(['password' => 'S3cret!', 'type' => 'password'], $attributes),
    );
}

it('lists the credentials created by the token owner', function () {
    $ctx = listActor();

    makeOrgCredential($ctx, ['name' => 'VPN corporativa', 'username' => 'vpn.user']);
    makeOrgCredential($ctx, ['name' => 'Router de planta', 'username' => 'admin']);

    $response = withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // Ordenado por nombre, no por fecha: es un buscador, no un feed.
    expect($response->json('data.0.name'))->toBe('Router de planta');
    expect($response->json('data.1.name'))->toBe('VPN corporativa');
});

it('never includes the password', function () {
    $ctx = listActor();
    makeOrgCredential($ctx, ['name' => 'VPN corporativa']);

    $response = withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials')
        ->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('password');
    expect($response->getContent())->not->toContain('S3cret!');
});

/**
 * El punto de seguridad del endpoint: un listado ES enumeracion. Si devolviera
 * todo lo que la politica de la organizacion permite ver, un token filtrado se
 * llevaria el indice de la boveda entera. Ademas /reveal las rechazaria luego.
 */
it('hides credentials created by other people in the same organization', function () {
    $ctx = listActor();

    $companero = verifiedUser();
    $companero->forceFill([
        'organization_id' => $ctx['organization']->id,
        'role'            => 'org_user',
        'account_type'    => 'enterprise',
    ])->save();

    makeOrgCredential($ctx, ['name' => 'Mia']);
    makeOrgCredential($ctx, ['name' => 'De un companero'], $companero);

    $response = withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.name'))->toBe('Mia');
});

it('searches by name, username and url', function () {
    $ctx = listActor();

    makeOrgCredential($ctx, ['name' => 'VPN corporativa', 'username' => 'vpn.user', 'url' => 'https://vpn.cybertec.co']);
    makeOrgCredential($ctx, ['name' => 'Router', 'username' => 'admin', 'url' => 'https://router.local']);

    $byName = withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials?q=corporativa')->assertOk();
    expect($byName->json('data'))->toHaveCount(1);

    $byUsername = withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials?q=vpn.user')->assertOk();
    expect($byUsername->json('data'))->toHaveCount(1);

    $byUrl = withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials?q=router.local')->assertOk();
    expect($byUrl->json('data.0.name'))->toBe('Router');
});

/** El OR de la busqueda tiene que ir agrupado, o se escapa del created_by. */
it('keeps the ownership filter while searching', function () {
    $ctx = listActor();

    $companero = verifiedUser();
    $companero->forceFill([
        'organization_id' => $ctx['organization']->id,
        'role'            => 'org_user',
        'account_type'    => 'enterprise',
    ])->save();

    makeOrgCredential($ctx, ['name' => 'VPN ajena', 'username' => 'vpn.user'], $companero);

    withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials?q=vpn')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('filters by category and by scope', function () {
    $ctx = listActor();

    makeOrgCredential($ctx, ['name' => 'De organizacion']);
    app(CredentialService::class)->createPersonal(
        $ctx['vaultCategory'],
        $ctx['user'],
        ['name' => 'De la boveda', 'password' => 'S3cret!', 'type' => 'password'],
    );

    $personal = withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials?scope=personal')->assertOk();
    expect($personal->json('data'))->toHaveCount(1);
    expect($personal->json('data.0.name'))->toBe('De la boveda');

    $organization = withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials?scope=organization')->assertOk();
    expect($organization->json('data.0.name'))->toBe('De organizacion');

    $byCategory = withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials?category_id='.$ctx['orgCategory']->id)->assertOk();
    expect($byCategory->json('data'))->toHaveCount(1);
});

it('requires the list ability, which read alone does not grant', function () {
    $ctx = listActor();
    makeOrgCredential($ctx, ['name' => 'VPN corporativa']);

    $soloRead = integrationToken($ctx['user'], [
        IntegrationAbility::ME_READ,
        IntegrationAbility::CREDENTIALS_READ,
    ]);

    withBearer($this, $soloRead)
        ->getJson('/api/integration/credentials')
        ->assertForbidden();
});

it('is paginated so a big vault cannot be pulled in one request', function () {
    $ctx = listActor();

    foreach (range(1, 30) as $i) {
        makeOrgCredential($ctx, ['name' => sprintf('Credencial %02d', $i)]);
    }

    withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials')
        ->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 30);

    // El tope duro no se puede saltar pidiendo mas.
    withBearer($this, integrationToken($ctx['user']))
        ->getJson('/api/integration/credentials?per_page=5000')
        ->assertOk()
        ->assertJsonCount(30, 'data');
});
