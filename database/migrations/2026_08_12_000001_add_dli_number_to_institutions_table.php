<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds the Ministry-managed DLI (Designated Learning Institution) number to
// institutions. Additive + idempotent so it is safe on databases already
// created by the initial institutions migration or the DynamicsDataMigrator.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutions') || Schema::hasColumn('institutions', 'dli_number')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table): void {
            $table->string('dli_number')->nullable()->after('qa_met_through');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('institutions') && Schema::hasColumn('institutions', 'dli_number')) {
            Schema::table('institutions', function (Blueprint $table): void {
                $table->dropColumn('dli_number');
            });
        }
    }
};
