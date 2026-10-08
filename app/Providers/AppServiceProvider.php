<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (
            $this->app->environment('production')
            && config('session.secure') !== true
        ) {
            throw new RuntimeException(
                'SIGA requiere SESSION_SECURE_COOKIE=true en producción.'
            );
        }
    }
}
