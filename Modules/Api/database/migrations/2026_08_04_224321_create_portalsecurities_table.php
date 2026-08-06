<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADEV_MSCRM/eqa_portalsecurityBase (sql_schema:table)
     */
    public function up(): void
    {
        Schema::create('portalsecurities', function (Blueprint $table) {
            $table->uuid('eqa_portalsecurity_id')->primary();

            $table->timestampTz('created_on')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('modified_on')->nullable();
            $table->uuid('modified_by')->nullable();
            $table->uuid('created_on_behalf_by')->nullable();
            $table->uuid('modified_on_behalf_by')->nullable();
            $table->uuid('organization_id')->nullable();

            $table->integer('statecode');
            $table->integer('statuscode')->nullable();

            $table->binary('version_number')->nullable();

            $table->integer('import_sequence_number')->nullable();
            $table->timestampTz('overridden_created_on')->nullable();
            $table->integer('time_zone_rule_version_number')->nullable();
            $table->integer('utc_conversion_time_zone_code')->nullable();

            $table->string('eqa_name', 320)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portalsecurities');
    }
};