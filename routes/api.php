<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\IntegrationTokenController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\ActivityController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\CredentialExportController;
use App\Http\Controllers\VaultCategoryController;
use App\Http\Controllers\VaultCredentialController;
use App\Http\Controllers\CredentialSearchController;
use App\Http\Controllers\CredentialVersionController;
use App\Http\Controllers\DomainVerificationController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\Integration\IntegrationCategoryController;
use App\Http\Controllers\Integration\IntegrationCredentialController;
use App\Http\Controllers\Integration\IntegrationMeController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationDivisionController;
use App\Http\Controllers\OrganizationDomainController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\PublicTokenController;
use App\Http\Controllers\SharedAccessTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth — Public routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('login/2fa', [AuthController::class, 'verify2fa'])->middleware('throttle:3,1');
    Route::post('2fa/setup', [TwoFactorController::class, 'setup'])->middleware('throttle:10,1');
    Route::post('2fa/enable', [TwoFactorController::class, 'enable'])->middleware('throttle:5,1');
    Route::post('invitations/accept', [InvitationController::class, 'accept'])->middleware('throttle:10,1');
    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
    Route::get('verify-reset-token', [PasswordResetController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');

    Route::get('google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('google/callback', [GoogleAuthController::class, 'callback']);
});

/*
|--------------------------------------------------------------------------
| Auth — Protected routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    \App\Http\Middleware\EnsurePersonalAccessToken::class,
    // Sella todo lo de abajo frente a los tokens de integracion: solo pueden
    // llegar a /api/integration/*. Ver EnsureSessionToken.
    \App\Http\Middleware\EnsureSessionToken::class,
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Auth — Rutas exentas de verificación TOTP
    | (logout, me, y el setup/enable/disable de 2FA necesitan funcionar
    |  aunque el usuario aún no haya completado la configuración)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::prefix('2fa')->group(function () {
            Route::post('disable', [TwoFactorController::class, 'disable']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Rutas protegidas — requieren TOTP configurado
    |--------------------------------------------------------------------------
    */
    Route::middleware(\App\Http\Middleware\EnsureTwoFactorSetup::class)->group(function () {

        /*
        |----------------------------------------------------------------------
        | Auth — Gestión de cuenta (requieren TOTP)
        |----------------------------------------------------------------------
        */
        Route::prefix('auth')->group(function () {
            Route::put('profile', [ProfileController::class, 'update']);
            Route::post('change-password', [ProfileController::class, 'changePassword']);
            Route::delete('account', [ProfileController::class, 'deleteAccount']);
            Route::get('activity', [ActivityController::class, 'index']);

            // Tokens de integración. Viven en el grupo humano (sesión + TOTP),
            // así que un token de integración no puede emitir otros.
            Route::get('integration-tokens', [IntegrationTokenController::class, 'index']);
            Route::post('integration-tokens', [IntegrationTokenController::class, 'store'])
                ->middleware('throttle:5,60');
            Route::delete('integration-tokens', [IntegrationTokenController::class, 'destroyAll']);
            Route::delete('integration-tokens/{token}', [IntegrationTokenController::class, 'destroy']);
        });

        /*
        |----------------------------------------------------------------------
        | Organizations
        |----------------------------------------------------------------------
        */
        Route::apiResource('organizations', OrganizationController::class);

        Route::prefix('organizations/{organization}')->group(function () {
            Route::apiResource('domains', OrganizationDomainController::class)
                ->only(['index', 'store', 'destroy']);

            Route::post('domains/{domain}/verify/initiate', [DomainVerificationController::class, 'initiate']);
            Route::post('domains/{domain}/verify/confirm', [DomainVerificationController::class, 'confirm']);

            Route::apiResource('users', OrganizationUserController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::patch('users/{user}/suspend', [OrganizationUserController::class, 'suspend']);
            Route::patch('users/{user}/activate', [OrganizationUserController::class, 'activate']);
            Route::post('users/{user}/make-admin', [OrganizationUserController::class, 'makeAdmin']);
            Route::post('users/{user}/make-user', [OrganizationUserController::class, 'makeUser']);

            Route::apiResource('divisions', OrganizationDivisionController::class);

            Route::apiResource('categories', CategoryController::class);

            Route::get('audit-logs', [AuditLogController::class, 'forOrganization']);

            Route::get('credentials', [CredentialSearchController::class, 'index']);
            Route::get('credentials/export', [CredentialExportController::class, 'export'])->middleware('throttle:3,60');
        });

        Route::get('audit-logs', [AuditLogController::class, 'index']);

        /*
        |----------------------------------------------------------------------
        | Credentials (scoped to category)
        |----------------------------------------------------------------------
        */
        Route::prefix('categories/{category}')->group(function () {
            Route::apiResource('credentials', CredentialController::class);
            Route::get('credentials/{credential}/reveal', [CredentialController::class, 'reveal']);

            Route::prefix('credentials/{credential}/versions')->group(function () {
                Route::get('/', [CredentialVersionController::class, 'index']);
                Route::get('{version}/reveal', [CredentialVersionController::class, 'reveal']);
                Route::post('{version}/restore', [CredentialVersionController::class, 'restore']);
            });
        });

        /*
        |----------------------------------------------------------------------
        | Personal Vault
        |----------------------------------------------------------------------
        */
        Route::prefix('vault/categories')->group(function () {
            Route::get('/', [VaultCategoryController::class, 'index']);
            Route::post('/', [VaultCategoryController::class, 'store']);
            Route::get('{category}', [VaultCategoryController::class, 'show']);
            Route::put('{category}', [VaultCategoryController::class, 'update']);
            Route::delete('{category}', [VaultCategoryController::class, 'destroy']);

            Route::prefix('{category}/credentials')->group(function () {
                Route::get('/', [VaultCredentialController::class, 'index']);
                Route::post('/', [VaultCredentialController::class, 'store']);
                Route::get('{credential}', [VaultCredentialController::class, 'show']);
                Route::put('{credential}', [VaultCredentialController::class, 'update']);
                Route::delete('{credential}', [VaultCredentialController::class, 'destroy']);
                Route::get('{credential}/reveal', [VaultCredentialController::class, 'reveal']);
            });
        });

        /*
        |----------------------------------------------------------------------
        | Images — Catálogo global del sistema
        |----------------------------------------------------------------------
        */
        Route::apiResource('images', ImageController::class);

        /*
        |----------------------------------------------------------------------
        | Shared Access Tokens (scoped to credential)
        |----------------------------------------------------------------------
        */
        Route::prefix('credentials/{credential}')->group(function () {
            Route::get('tokens', [SharedAccessTokenController::class, 'index']);
            Route::post('tokens', [SharedAccessTokenController::class, 'store']);
            Route::patch('tokens/{token}/revoke', [SharedAccessTokenController::class, 'revoke']);
        });

    }); // EnsureTwoFactorSetup

});

/*
|--------------------------------------------------------------------------
| Integration API — consumida por apps externas (Axis) con token de integración
|--------------------------------------------------------------------------
|
| EnsureTwoFactorSetup va aquí sin exención: solo lee el booleano
| two_factor_enabled, que el humano ya tiene en true porque emitir el token
| exige un OTP en vivo. Como efecto secundario se gana un interruptor de
| emergencia — si desactiva su 2FA, la integración deja de funcionar.
|
| 'throttle:' tiene que ir DESPUÉS de 'auth:sanctum', o $request->user() es null
| y los limitadores por token degradan a por-IP (una sola IP para todos).
|
*/
Route::prefix('integration')
    ->middleware([
        'auth:sanctum',
        \App\Http\Middleware\EnsurePersonalAccessToken::class,
        \App\Http\Middleware\EnsureTwoFactorSetup::class,
        'throttle:integration',
    ])
    ->group(function () {
        Route::get('me', [IntegrationMeController::class, 'show'])
            ->middleware('integration.ability:integration:me.read');

        Route::get('categories', [IntegrationCategoryController::class, 'index'])
            ->middleware('integration.ability:integration:categories.read');

        Route::prefix('credentials')->group(function () {
            // Antes que {credential}: si no, el comodin se traga la ruta vacia.
            Route::get('/', [IntegrationCredentialController::class, 'index'])
                ->middleware('integration.ability:integration:credentials.list');

            Route::post('/', [IntegrationCredentialController::class, 'store'])
                ->middleware(['integration.ability:integration:credentials.create', 'throttle:integration-write']);

            Route::get('{credential}', [IntegrationCredentialController::class, 'show'])
                ->middleware('integration.ability:integration:credentials.read');

            Route::put('{credential}', [IntegrationCredentialController::class, 'update'])
                ->middleware(['integration.ability:integration:credentials.update', 'throttle:integration-write']);

            Route::get('{credential}/reveal', [IntegrationCredentialController::class, 'reveal'])
                ->middleware(['integration.ability:integration:credentials.reveal', 'throttle:integration-reveal']);

            Route::delete('{credential}', [IntegrationCredentialController::class, 'destroy'])
                ->middleware(['integration.ability:integration:credentials.delete', 'throttle:integration-write']);
        });
    });

/*
|--------------------------------------------------------------------------
| Public — Shared token consumption (no auth required)
|--------------------------------------------------------------------------
*/
Route::get('shared/{token}', [PublicTokenController::class, 'info'])
    ->middleware('throttle:20,1');

Route::post('shared/{token}/claim', [PublicTokenController::class, 'claim'])
    ->middleware('throttle:10,1');
