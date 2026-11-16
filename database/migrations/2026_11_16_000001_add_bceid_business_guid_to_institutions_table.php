<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BCeID Business GUID for the institution's BCeID business account. Required when a
// ministry user creates an institution. Additive + idempotent so it is safe on
// databases already loaded by the DynamicsDataMigrator; existing rows stay NULL.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutions') || Schema::hasColumn('institutions', 'bceid_business_guid')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table): void {
            $table->string('bceid_business_guid')->nullable()->index()->after('bc_incorporation_number');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('institutions') && Schema::hasColumn('institutions', 'bceid_business_guid')) {
            Schema::table('institutions', function (Blueprint $table): void {
                $table->dropColumn('bceid_business_guid');
            });
        }
    }
};
