<?php

namespace App\Providers;

use App\Importing\Personas\DatabasePersonImportReferenceLookup;
use App\Importing\Personas\PersonImportReferenceLookup;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PersonImportReferenceLookup::class,
            static function (Application $app): PersonImportReferenceLookup {
                return new DatabasePersonImportReferenceLookup(
                    $app->make('db')->connection()
                );
            }
        );
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
