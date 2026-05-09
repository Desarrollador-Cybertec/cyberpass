<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Credential;
use App\Models\Image;
use App\Models\Organization;
use App\Models\SharedAccessToken;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\CredentialPolicy;
use App\Policies\ImagePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\SharedAccessTokenPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
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
    }

    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Credential::class, CredentialPolicy::class);
        Gate::policy(Image::class, ImagePolicy::class);
        Gate::policy(SharedAccessToken::class, SharedAccessTokenPolicy::class);
    }
}
