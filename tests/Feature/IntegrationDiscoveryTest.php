<?php

use App\Models\Category;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

it('reports the identity the token belongs to', function () {
    $organization = Organization::create(['name' => 'Cybertec', 'slug' => 'cybertec-me']);

    $user = verifiedUser();
    $user->forceFill([
        'organization_id' => $organization->id,
        'role'            => 'org_admin',
        'account_type'    => 'enterprise',
    ])->save();

    withBearer($this, integrationToken($user))
        ->getJson('/api/integration/me')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('organization.name', 'Cybertec')
        ->assertJsonPath('token.name', 'test integration')
        ->assertJsonStructure(['token' => ['abilities', 'expires_at', 'last_used_at']]);
});

it('reports a null organization for a personal account', function () {
    $user = verifiedUser();
    $user->forceFill(['role' => 'user', 'account_type' => 'personal'])->save();

    withBearer($this, integrationToken($user))
        ->getJson('/api/integration/me')
        ->assertOk()
        ->assertJsonPath('organization', null);
});

it('lists personal and organization categories in one call, with their scope', function () {
    $organization = Organization::create(['name' => 'Cybertec', 'slug' => 'cybertec-cats']);

    $user = verifiedUser();
    $user->forceFill([
        'organization_id' => $organization->id,
        'role'            => 'org_admin',
        'account_type'    => 'enterprise',
    ])->save();

    Category::create(['organization_id' => $organization->id, 'name' => 'Infra']);
    Category::create(['user_id' => $user->id, 'name' => 'Mis cosas']);

    // Ni la categoria de otra organizacion ni la boveda de otra persona.
    $otherOrg = Organization::create(['name' => 'Ajena', 'slug' => 'ajena-cats']);
    Category::create(['organization_id' => $otherOrg->id, 'name' => 'No visible']);
    Category::create(['user_id' => verifiedUser()->id, 'name' => 'Tampoco visible']);

    $response = withBearer($this, integrationToken($user))
        ->getJson('/api/integration/categories')
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toEqualCanonicalizing(['Infra', 'Mis cosas']);

    $byName = collect($response->json('data'))->keyBy('name');

    expect($byName['Infra']['scope'])->toBe('organization')
        ->and($byName['Infra']['organization']['name'])->toBe('Cybertec')
        ->and($byName['Infra']['can_create_credential'])->toBeTrue()
        ->and($byName['Mis cosas']['scope'])->toBe('personal')
        ->and($byName['Mis cosas']['can_create_credential'])->toBeTrue();
});

it('shows a personal-account user only their own vault', function () {
    $organization = Organization::create(['name' => 'Cybertec', 'slug' => 'cybertec-personal']);
    Category::create(['organization_id' => $organization->id, 'name' => 'Infra']);

    $user = verifiedUser();
    // account_type personal: CredentialPolicy le niega todo lo de organizacion,
    // asi que ni siquiera debe verlo aunque tenga organization_id.
    $user->forceFill([
        'organization_id' => $organization->id,
        'role'            => 'user',
        'account_type'    => 'personal',
    ])->save();

    Category::create(['user_id' => $user->id, 'name' => 'Mis cosas']);

    $data = withBearer($this, integrationToken($user))
        ->getJson('/api/integration/categories')
        ->assertOk()
        ->json('data');

    expect(collect($data)->pluck('name')->all())->toBe(['Mis cosas']);
});
