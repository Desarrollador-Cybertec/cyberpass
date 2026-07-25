<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Credential;
use App\Models\CredentialVersion;
use App\Models\Image;
use App\Models\Organization;
use App\Models\PersonalAccessToken;
use App\Models\SharedAccessToken;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CredentialPolicy;
use App\Policies\CredentialVersionPolicy;
use App\Policies\ImagePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\SharedAccessTokenPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Google2FA::class, function () {
            $otp = new Google2FA;
            $otp->setWindow(config('google2fa.window', 1));

            return $otp;
        });

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    public function boot(): void
    {
        $this->registerIntegrationTokenLifetime();
        $this->registerRateLimiters();

        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Credential::class, CredentialPolicy::class);
        Gate::policy(CredentialVersion::class, CredentialVersionPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Image::class, ImagePolicy::class);
        Gate::policy(SharedAccessToken::class, SharedAccessTokenPolicy::class);
    }

    /**
     * Exime a los tokens de integracion del tope global de expiracion.
     *
     * config('sanctum.expiration') es global y pisa el expires_at de cada
     * token, asi que hoy ningun PAT puede vivir mas de 20 minutos. Ponerlo en
     * null seria peor: nueve sitios hacen (int) config('sanctum.expiration', 20)
     * y (int) null === 0 -> max(1, 0) -> sesiones humanas de 1 minuto.
     *
     * Guard::isValidAccessToken() deja este callback SOBRESCRIBIR su resultado
     * (vendor/laravel/sanctum/src/Guard.php), asi que se puede exceptuar una
     * sola clase de token. Para todo lo demas se devuelve $isValid tal cual:
     * el camino humano queda bit a bit como estaba.
     */
    private function registerIntegrationTokenLifetime(): void
    {
        Sanctum::authenticateAccessTokensUsing(
            function ($accessToken, bool $isValid): bool {
                if (! $accessToken instanceof PersonalAccessToken || ! $accessToken->isIntegration()) {
                    return $isValid;
                }

                // Se replica el chequeo de proveedor que hace el Guard, porque
                // al sobrescribir $isValid lo estamos descartando.
                if (! $accessToken->tokenable instanceof User) {
                    return false;
                }

                // Revocar un token de integracion = borrar la fila, asi que
                // aqui solo queda comprobar su propio vencimiento.
                return ! $accessToken->expires_at || ! $accessToken->expires_at->isPast();
            }
        );
    }

    /**
     * El trafico de integracion llega desde UNA IP (el servidor de Axis) para
     * MUCHOS usuarios, asi que limitar por IP no separa a nadie: la clave es el
     * id del token. Ojo, 'throttle:' tiene que ir despues de 'auth:sanctum' en
     * la lista de middleware o $request->user() es null y todo degrada a IP.
     */
    private function registerRateLimiters(): void
    {
        $byToken = fn (Request $request) => 'itk:'.($request->user()?->currentAccessToken()?->id ?? $request->ip());

        RateLimiter::for('integration', fn (Request $r) => Limit::perMinute(60)->by($byToken($r)));

        RateLimiter::for('integration-write', fn (Request $r) => Limit::perMinute(30)->by($byToken($r)));

        // El unico endpoint que devuelve secretos en claro. El tope diario hace
        // que vaciar una boveda con un token robado tarde semanas y deje un
        // rastro ruidoso en audit_logs.
        RateLimiter::for('integration-reveal', fn (Request $r) => [
            Limit::perMinute(10)->by($byToken($r)),
            Limit::perDay(300)->by($byToken($r)),
        ]);
    }
}
