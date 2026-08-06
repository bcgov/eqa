<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADEV_MSCRM/eqa_eqalocationBase (sql_schema:table)
     */
    public function up(): void
    {
        Schema::create('eqalocations', function (Blueprint $table) {
            $table->uuid('eqa_eqalocation_id')->primary();

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

            $table->uuid('processid')->nullable();
            $table->uuid('stageid')->nullable();
            $table->string('traversedpath', 2500)->nullable();

            $table->string('eqa_city', 100)->nullable();
            $table->integer('eqa_country')->nullable();

            $table->uuid('eqa_dba_namenew')->nullable();
            $table->foreign('eqa_dba_namenew')
                ->references('eqa_dba_id')->on('eqa_dbas')
                ->nullOnDelete();

            $table->text('eqa_description')->nullable();
            $table->string('eqa_email', 200)->nullable();

            // References AccountBase which is not part of this migration's scope (external entity).
            $table->uuid('eqa_institution_name')->nullable();

            $table->string('eqa_legacy_id', 60)->nullable();
            $table->string('eqa_location_name', 400)->nullable();
            $table->integer('eqa_parent_type')->nullable();
            $table->string('eqa_postal_code', 40)->nullable();
            $table->boolean('eqa_primary_location')->nullable();
            $table->integer('eqa_province')->nullable();
            $table->string('eqa_street1', 500)->nullable();
            $table->string('eqa_street2', 500)->nullable();
            $table->string('eqa_street3', 500)->nullable();
            $table->string('eqa_website_url', 400)->nullable();

            $table->index('owner_id');
            $table->index('owning_business_unit');
            $table->index('eqa_dba_namenew');
            $table->index('eqa_institution_name');
            $table->index('statecode');
            $table->index('statuscode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eqalocations');
    }
};