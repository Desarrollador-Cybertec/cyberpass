<?php

use App\Helpers\IntegrationAbility;
use App\Models\Category;
use App\Models\Organization;
use App\Services\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config()->set('app.credential_encryption_key', base64_encode(random_bytes(32)));
});

function enterpriseFixture(): array
{
    $organization = Organization::create(['name' => 'Cybertec', 'slug' => 'cybertec-enf']);

    $user = verifiedUser(['password' => 'super-secret-123']);
    $user->forceFill([
        'organization_id' => $organization->id,
        'role'            => 'org_admin',
        'account_type'    => 'enterprise',
    ])->save();

    $category = Category::create(['organization_id' => $organization->id, 'name' => 'Infra']);

    $credential = app(CredentialService::class)->create($category, $organization, $user, [
        'name'     => 'VPN',
        'password' => 'super-secret',
    ]);

    return compact('organization', 'user', 'category', 'credential');
}

/**
 * El test que impide la escalada de privilegios.
 *
 * Sin EnsureSessionToken, un token de integración llegaría a TODO el árbol de
 * rutas existente: podría cambiar la contraseña del usuario, desactivar su 2FA,
 * borrar su cuenta y — lo peor — generar un enlace público SIN autenticación
 * para cualquier credencial de la organización, porque
 * SharedAccessTokenPolicy::create() lo permite a cualquier org_user.
 */
it('rejects an integration token on every legacy route', function (string $method, callable $path) {
    ['user' => $user, 'category' => $category, 'credential' => $credential] = enterpriseFixture();

    $token = integrationToken($user, IntegrationAbility::all());
    $url = $path($category->id, $credential->id, $user->organization_id);

    withBearer($this, $token)
        ->json($method, $url)
        ->assertForbidden()
        ->assertJsonPath('error_code', 'session_token_required');
})->with([
    'me'                => ['GET', fn () => '/api/auth/me'],
    'change password'   => ['POST', fn () => '/api/auth/change-password'],
    'disable 2fa'       => ['POST', fn () => '/api/auth/2fa/disable'],
    'delete account'    => ['DELETE', fn () => '/api/auth/account'],
    'create credential' => ['POST', fn ($cat) => "/api/categories/{$cat}/credentials"],
    'reveal credential' => ['GET', fn ($cat, $cred) => "/api/categories/{$cat}/credentials/{$cred}/reveal"],
    'vault categories'  => ['GET', fn () => '/api/vault/categories'],
    'share link'        => ['POST', fn ($cat, $cred) => "/api/credentials/{$cred}/tokens"],
    'org export'        => ['GET', fn ($cat, $cred, $org) => "/api/organizations/{$org}/credentials/export"],
    'audit logs'        => ['GET', fn () => '/api/audit-logs'],
]);

it('rejects a session token on the integration surface', function () {
    ['user' => $user] = enterpriseFixture();

    $temp = $this->postJson('/api/auth/login', [
        'email' => $user->email, 'password' => 'super-secret-123',
    ])->assertOk()->json('temp_token');

    $session = $this->postJson('/api/auth/login/2fa', [
        'temp_token' => $temp,
        'otp'        => app(Google2FA::class)->getCurrentOtp($user->two_factor_secret),
    ])->assertOk()->json('token');

    withBearer($this, $session)
        ->getJson('/api/integration/me')
        ->assertForbidden()
        ->assertJsonPath('error_code', 'integration_token_required');
});

it('names the missing ability when the token lacks it', function () {
    ['user' => $user] = enterpriseFixture();

    // Token sin CREDENTIALS_REVEAL: puede crear, pero no leer secretos.
    $token = integrationToken($user, [
        IntegrationAbility::ME_READ,
        IntegrationAbility::CATEGORIES_READ,
        IntegrationAbility::CREDENTIALS_CREATE,
    ]);

    withBearer($this, $token)
        ->getJson('/api/integration/categories')
        ->assertOk();

    withBearer($this, $token)
        ->getJson('/api/integration/credentials/1/reveal')
        ->assertForbidden()
        ->assertJsonPath('error_code', 'integration_ability_missing')
        ->assertJsonPath('ability', IntegrationAbility::CREDENTIALS_REVEAL);
});

it('still denies an integration token that somehow carries the wildcard', function () {
    ['user' => $user] = enterpriseFixture();

    // Defensa en profundidad: si un bug futuro emitiera un token de integración
    // con '*', $token->can() lo daría por bueno. El middleware usa in_array.
    $token = integrationToken($user, ['*']);

    withBearer($this, $token)
        ->getJson('/api/integration/me')
        ->assertForbidden()
        ->assertJsonPath('error_code', 'integration_ability_missing');
});

it('stops working when the user disables two-factor authentication', function () {
    ['user' => $user] = enterpriseFixture();

    $token = integrationToken($user);

    withBearer($this, $token)->getJson('/api/integration/me')->assertOk();

    // Interruptor de emergencia gratis: desactivar el 2FA corta la integración.
    $user->forceFill(['two_factor_enabled' => false])->save();

    withBearer($this, $token)
        ->getJson('/api/integration/me')
        ->assertForbidden()
        ->assertJsonPath('error_code', 'two_factor_setup_required');
});
