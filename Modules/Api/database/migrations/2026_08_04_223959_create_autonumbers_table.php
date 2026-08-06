<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADEV_MSCRM/eqa_autonumberBase (sql_schema:table)
     */
    public function up(): void
    {
        Schema::create('autonumbers', function (Blueprint $table) {
            $table->uuid('eqa_autonumber_id')->primary();

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

            $table->integer('eqa_account_increment')->nullable();
            $table->integer('eqa_account_number')->nullable();
            $table->string('eqa_account_prefix', 20)->nullable();
            $table->string('eqa_account_suffix', 20)->nullable();

            $table->integer('eqa_application_increment')->nullable();
            $table->integer('eqa_application_number')->nullable();
            $table->string('eqa_application_prefix', 20)->nullable();
            $table->string('eqa_application_suffix', 20)->nullable();

            $table->integer('eqa_invoice_increment')->nullable();
            $table->integer('eqa_invoice_number')->nullable();
            $table->string('eqa_invoice_prefix', 20)->nullable();
            $table->string('eqa_invoice_suffix', 20)->nullable();

            $table->index('owner_id');
            $table->index('owning_business_unit');
            $table->index('statecode');
            $table->index('statuscode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autonumbers');
    }
};