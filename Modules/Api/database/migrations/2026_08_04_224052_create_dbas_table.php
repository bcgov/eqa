<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADEV_MSCRM/eqa_dbaBase (sql_schema:table)
     */
    public function up(): void
    {
        Schema::create('dbas', function (Blueprint $table) {
            $table->uuid('eqa_dba_id')->primary();

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

            $table->string('eqa_name', 400)->nullable();
            $table->string('email_address', 512)->nullable();
            $table->string('eqa_city', 200)->nullable();
            $table->integer('eqa_country')->nullable();
            $table->text('eqa_description')->nullable();
            $table->string('eqa_email', 200)->nullable();
            $table->uuid('eqa_institution_name')->nullable();
            $table->string('eqa_postal_code', 40)->nullable();
            $table->integer('eqa_province')->nullable();
            $table->string('eqa_street1', 500)->nullable();
            $table->string('eqa_street2', 500)->nullable();
            $table->string('eqa_street3', 500)->nullable();
            $table->string('eqa_website_url', 400)->nullable();

            $table->index('eqa_institution_name');
            $table->index('owner_id');
            $table->index('statecode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dbas');
    }
};