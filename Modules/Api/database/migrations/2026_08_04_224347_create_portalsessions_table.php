<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADEV_MSCRM/eqa_portalsessionBase (sql_schema:table)
     */
    public function up(): void
    {
        Schema::create('portalsessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->timestampTz('created_on')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('modified_on')->nullable();
            $table->uuid('modified_by')->nullable();
            $table->uuid('created_on_behalf_by')->nullable();
            $table->uuid('modified_on_behalf_by')->nullable();

            $table->uuid('owner_id');
            $table->integer('owner_id_type');
            $table->uuid('owning_business_unit')->nullable();

            $table->integer('statecode');
            $table->integer('statuscode')->nullable();

            $table->binary('version_number')->nullable();
            $table->integer('import_sequence_number')->nullable();
            $table->timestampTz('overridden_created_on')->nullable();
            $table->integer('time_zone_rule_version_number')->nullable();
            $table->integer('utc_conversion_time_zone_code')->nullable();

            $table->string('name', 600)->nullable();
            $table->timestampTz('end_date')->nullable();
            $table->uuid('portal_profile_id')->nullable();
            $table->timestampTz('start_date')->nullable();

            $table->foreign('portal_profile_id')
                ->references('id')
                ->on('portalprofiles')
                ->nullOnDelete();

            $table->index('owner_id');
            $table->index('statecode');
            $table->index('statuscode');
            $table->index('portal_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portalsessions');
    }
};