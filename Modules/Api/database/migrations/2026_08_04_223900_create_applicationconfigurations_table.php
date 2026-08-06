<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADEV_MSCRM/eqa_applicationconfigurationBase (sql_schema:table)
     */
    public function up(): void
    {
        Schema::create('applicationconfigurations', function (Blueprint $table) {
            $table->uuid('eqa_applicationconfiguration_id')->primary();

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

            $table->string('eqa_name', 200)->nullable();
            $table->uuid('processid')->nullable();
            $table->uuid('stageid')->nullable();
            $table->string('traversedpath', 2500)->nullable();

            $table->timestampTz('eqa_anniversary_date')->nullable();
            $table->decimal('eqa_annual_designation_fee', 19, 4)->nullable();
            $table->uuid('transaction_currency_id')->nullable();
            $table->decimal('exchange_rate', 23, 10)->nullable();
            $table->decimal('eqa_annualdesignationfee_base', 19, 4)->nullable();
            $table->decimal('eqa_application_fee', 19, 4)->nullable();
            $table->decimal('eqa_applicationfee_base', 19, 4)->nullable();
            $table->timestampTz('eqa_applications_year_end')->nullable();
            $table->boolean('eqa_fee_proration')->nullable();
            $table->integer('eqa_numeric_value')->nullable();
            $table->timestampTz('eqa_designation_anniversary_date')->nullable();
            $table->integer('eqa_draft_expiry_days')->nullable();

            $table->index('created_by');
            $table->index('modified_by');
            $table->index('organization_id');
            $table->index('processid');
            $table->index('stageid');
            $table->index('transaction_currency_id');
            $table->index('statecode');
            $table->index('statuscode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicationconfigurations');
    }
};