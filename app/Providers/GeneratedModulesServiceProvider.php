<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the generated modules with the destination application.
 *
 * The real EQA schema (institutions, applications, campuses, institution_users,
 * dbas, invoices) lives in the base `database/migrations` directory, so it is
 * created by a plain `php artisan migrate`. The per-module `database/migrations`
 * folders hold reverse-engineered legacy scaffolding that is NOT part of the
 * running portal and is intentionally not loaded here — several of those files
 * declare foreign keys to tables that do not exist, which would abort migrate.
 */
class GeneratedModulesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Intentionally does not load module migrations. See class docblock.
    }
}