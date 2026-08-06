<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADEV_MSCRM/eqa_testBase (sql_schema:table)
     */
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table) {
            $table->uuid('eqa_test_id')->primary();

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

            $table->string('eqa_name', 200)->nullable();

            $table->index('statecode');
            $table->index('statuscode');
            $table->index('owner_id');
            $table->index('owning_business_unit');
            $table->index('created_on');
            $table->index('modified_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};