<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationDivisionController;
use App\Http\Controllers\OrganizationDomainController;
use App\Http\Controllers\OrganizationUserController;
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

    Route::get('google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('google/callback', [GoogleAuthController::class, 'callback']);
});

/*
|--------------------------------------------------------------------------
| Auth — Protected routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::prefix('2fa')->group(function () {
            Route::post('setup', [TwoFactorController::class, 'setup']);
            Route::post('enable', [TwoFactorController::class, 'enable']);
            Route::post('disable', [TwoFactorController::class, 'disable']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Organizations
    |--------------------------------------------------------------------------
    */
    Route::apiResource('organizations', OrganizationController::class);

    Route::prefix('organizations/{organization}')->group(function () {
        Route::apiResource('domains', OrganizationDomainController::class)
            ->only(['index', 'store', 'destroy']);

        Route::apiResource('users', OrganizationUserController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('divisions', OrganizationDivisionController::class);
    });
});
