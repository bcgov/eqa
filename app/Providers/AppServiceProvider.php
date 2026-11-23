<?php

namespace App\Providers;

use App\Models\Util;
use Illuminate\Support\ServiceProvider;

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
        // Admin-maintained settings (Maintenance > Utils) override the config/.env defaults.
        Util::applyToConfig();
    }
}
