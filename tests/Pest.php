<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Emite un token de integracion REAL y devuelve su texto plano.
 *
 * Ojo: Sanctum::actingAs() NO sirve para probar tokens de integracion. Monta un
 * mock de Mockery y ni siquiera pasa por el Guard, asi que isIntegration()
 * devuelve falsy y el callback authenticateAccessTokensUsing() nunca corre: un
 * test escrito con actingAs() pasaria sin afirmar nada. Hay que usar
 * ->withToken($plain) con el valor que devuelve este helper.
 *
 * @param  list<string>|null  $abilities
 */
function integrationToken(
    App\Models\User $user,
    ?array $abilities = null,
    ?DateTimeInterface $expiresAt = null,
): string {
    $new = $user->createToken(
        'test integration',
        $abilities ?? App\Helpers\IntegrationAbility::defaults(),
        $expiresAt ?? now()->addYear(),
    );

    // createToken() no rellena 'type': no esta en el $fillable de Sanctum.
    $new->accessToken->forceFill(['type' => App\Models\PersonalAccessToken::TYPE_INTEGRATION])->save();

    return $new->plainTextToken;
}

/**
 * Cambia el bearer entre peticiones DENTRO de un mismo test.
 *
 * Laravel conserva en el guard el usuario ya resuelto de una peticion a la
 * siguiente del mismo test, asi que un ->withToken() posterior se ignora en
 * silencio y la peticion sigue autenticada con el token anterior. Sin este
 * forgetGuards(), un test que alterna token de sesion y de integracion afirma
 * justo lo contrario de lo que cree estar afirmando.
 */
function withBearer(object $test, string $token): object
{
    // app() y no $test->app: esa propiedad es protected en el TestCase, y en un
    // test el contenedor global ES la misma instancia de aplicacion.
    app('auth')->forgetGuards();

    return $test->withToken($token);
}

/** Un usuario listo para operar: 2FA activo, que es lo que exige la API. */
function verifiedUser(array $attributes = []): App\Models\User
{
    return App\Models\User::factory()->create(array_merge([
        'two_factor_enabled'      => true,
        'two_factor_confirmed_at' => now(),
        'two_factor_secret'       => app(PragmaRX\Google2FA\Google2FA::class)->generateSecretKey(),
    ], $attributes));
}
