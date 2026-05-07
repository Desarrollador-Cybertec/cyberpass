<?php

namespace App\Providers;

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
        //
    }
}
