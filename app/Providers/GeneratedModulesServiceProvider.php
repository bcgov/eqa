<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the generated modules with the destination application: every
 * `Modules/{Name}/database/migrations` directory is loaded so its schema is
 * created against postgres-dest by `php artisan migrate`.
 */
class GeneratedModulesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (glob(base_path('Modules/*/database/migrations'), GLOB_ONLYDIR) ?: [] as $path) {
            $this->loadMigrationsFrom($path);
        }
    }
}