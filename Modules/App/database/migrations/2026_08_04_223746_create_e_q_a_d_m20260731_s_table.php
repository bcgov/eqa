<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Source artifact "EQADM-20260731" is an external SQL Server backup
     * (EQADM-20260731.bak, ~9.9MB, modified 2026-07-31T19:10:45+00:00) that
     * requires a manual restore before any data can be synchronized:
     *
     *   RESTORE DATABASE [EQADM-20260731]
     *   FROM DISK = N'/var/www/html/_sources/db/EQADM-20260731.bak'
     *
     * then attach a SQL connection and set MIGRATION_SQL_CONNECTION.
     *
     * No IR nodes were supplied for this artifact, so this migration only
     * establishes a tracking table used to record the restore/attach status
     * of the external backup. It intentionally creates no business tables
     * until the backup has been restored and re-analyzed.
     */
    public function up(): void
    {
        Schema::create('eqadm20260731s', function (Blueprint $table) {
            $table->id();
            $table->string('source_ref')->unique();
            $table->string('database_name');
            $table->string('connector');
            $table->string('kind');
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->timestampTz('modified_at')->nullable();
            $table->boolean('requires_restore')->default(true);
            $table->boolean('external_source')->default(true);
            $table->text('restore_hint')->nullable();
            $table->timestampTz('restored_at')->nullable();
            $table->timestamps();
        });

        DB::table('eqadm20260731s')->insert([
            'source_ref' => 'EQADM-20260731.bak',
            'database_name' => 'EQADM-20260731',
            'connector' => 'sql_backup',
            'kind' => 'database_backup',
            'file_path' => '/var/www/html/_sources/db/EQADM-20260731.bak',
            'bytes' => 9902080,
            'modified_at' => '2026-07-31 19:10:45+00',
            'requires_restore' => true,
            'external_source' => true,
            'restore_hint' => "RESTORE DATABASE [EQADM-20260731] FROM DISK = N'/var/www/html/_sources/db/EQADM-20260731.bak' then attach a SQL connection and set MIGRATION_SQL_CONNECTION.",
            'restored_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eqadm20260731s');
    }
};