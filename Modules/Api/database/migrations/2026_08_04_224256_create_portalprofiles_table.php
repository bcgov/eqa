<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portalprofiles', function (Blueprint $table) {
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

            $table->string('name', 300)->nullable();
            $table->string('account_dluid', 12)->nullable();
            $table->string('bceid', 40)->nullable();
            $table->string('business_dluid', 12)->nullable();
            $table->string('business_guid', 64)->nullable();
            $table->string('business_legal_name', 500)->nullable();

            $table->uuid('contact_id')->nullable();
            $table->boolean('delegated_administrator')->nullable();
            $table->string('email', 500)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->uuid('institution_id')->nullable();
            $table->timestampTz('lastlogon')->nullable();
            $table->boolean('managed_disabled')->nullable();

            $table->uuid('portal_security_id')->nullable();
            $table->boolean('removed_from_bceid')->nullable();
            $table->string('surname', 100)->nullable();
            $table->boolean('suspended')->nullable();
            $table->boolean('terms_of_use')->nullable();
            $table->string('user_guid', 500)->nullable();
            $table->boolean('contact_notified')->nullable();
            $table->timestampTz('todays_date')->nullable();

            $table->foreign('portal_security_id')
                ->references('id')->on('portalsecurities')
                ->nullOnDelete();

            $table->index('owner_id');
            $table->index('contact_id');
            $table->index('institution_id');
            $table->index('statecode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portalprofiles');
    }
};