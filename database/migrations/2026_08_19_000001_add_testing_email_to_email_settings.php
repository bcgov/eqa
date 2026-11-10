<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds a "testing_email" address to the global email settings. When the master
// switch (sending_enabled) is OFF but a testing_email is set, every notification
// is redirected to that address instead of the recipient configured on the
// template/process — a safe way to preview live notifications without emailing
// real institutions. Additive + idempotent.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_settings') && ! Schema::hasColumn('email_settings', 'testing_email')) {
            Schema::table('email_settings', function (Blueprint $table): void {
                $table->string('testing_email')->nullable()->after('sending_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_settings') && Schema::hasColumn('email_settings', 'testing_email')) {
            Schema::table('email_settings', function (Blueprint $table): void {
                $table->dropColumn('testing_email');
            });
        }
    }
};
