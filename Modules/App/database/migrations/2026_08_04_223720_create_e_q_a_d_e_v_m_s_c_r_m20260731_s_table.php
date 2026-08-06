<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Source artifact "EQADEV_MSCRM-20260731" is an external SQL Server backup
     * (.bak) that has not yet been restored/attached. No IR nodes (tables,
     * columns, keys) were available at generation time, so this migration
     * only registers a placeholder tracking table documenting the pending
     * restore. Replace/extend this migration once the backup has been
     * restored and a live schema can be introspected.
     */
    public function up(): void
    {
        Schema::create('eqadev_mscrm_20260731_s', function (Blueprint $table) {
            $table->id();

            // Provenance of the originating SQL Server backup source.
            $table->string('source_connector')->default('sql_backup');
            $table->string('source_kind')->default('database_backup');
            $table->string('source_name')->default('EQADEV_MSCRM-20260731');
            $table->string('source_ref')->default('EQADEV_MSCRM-20260731.bak');

            // Metadata captured from the backup file at discovery time.
            $table->string('source_file')->nullable();
            $table->string('source_database')->nullable();
            $table->unsignedBigInteger('source_bytes')->nullable();
            $table->timestampTz('source_modified_at')->nullable();

            $table->boolean('requires_restore')->default(true);
            $table->boolean('external_source')->default(true);
            $table->text('restore_hint')->nullable();

            $table->string('migration_sql_connection')->nullable();
            $table->timestampTz('restored_at')->nullable();
            $table->string('status')->default('pending_restore');

            $table->timestamps();
        });

        Schema::table('eqadev_mscrm_20260731_s', function (Blueprint $table) {
            $table->index('status');
            $table->index('source_ref');
        });

        DB::table('eqadev_mscrm_20260731_s')->insert([
            'source_connector' => 'sql_backup',
            'source_kind' => 'database_backup',
            'source_name' => 'EQADEV_MSCRM-20260731',
            'source_ref' => 'EQADEV_MSCRM-20260731.bak',
            'source_file' => '/var/www/html/_sources/db/EQADEV_MSCRM-20260731.bak',
            'source_database' => 'EQADEV_MSCRM-20260731',
            'source_bytes' => 2004077568,
            'source_modified_at' => '2026-07-31 19:11:34',
            'requires_restore' => true,
            'external_source' => true,
            'restore_hint' => "RESTORE DATABASE [EQADEV_MSCRM-20260731] FROM DISK = N'/var/www/html/_sources/db/EQADEV_MSCRM-20260731.bak' then attach a SQL connection and set MIGRATION_SQL_CONNECTION.",
            'migration_sql_connection' => env('MIGRATION_SQL_CONNECTION'),
            'status' => 'pending_restore',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eqadev_mscrm_20260731_s');
    }
};