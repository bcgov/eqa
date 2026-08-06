<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicationprocesses', function (Blueprint $table) {
            $table->uuid('business_process_flow_instance_id')->primary();
            $table->timestampTz('created_on', 3)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('modified_on', 3)->nullable();
            $table->uuid('modified_by')->nullable();
            $table->uuid('created_on_behalf_by')->nullable();
            $table->uuid('modified_on_behalf_by')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->integer('statecode');
            $table->integer('statuscode')->nullable();
            $table->timestampTz('version_number')->nullable();
            $table->integer('import_sequence_number')->nullable();
            $table->timestampTz('overridden_created_on', 3)->nullable();
            $table->integer('time_zone_rule_version_number')->nullable();
            $table->integer('utc_conversion_time_zone_code')->nullable();
            $table->string('bpf_name', 200)->nullable();
            $table->uuid('active_stage_id')->nullable();
            $table->uuid('process_id')->nullable();
            $table->string('traversed_path', 2500)->nullable();
            $table->timestampTz('completed_on', 3)->nullable();
            $table->timestampTz('active_stage_started_on', 3)->nullable();
            $table->uuid('bpf_eqa_eqaapplicationid')->nullable();
            $table->integer('bpf_duration')->nullable();

            $table->index('created_by');
            $table->index('modified_by');
            $table->index('organization_id');
            $table->index('active_stage_id');
            $table->index('process_id');
            $table->index('bpf_eqa_eqaapplicationid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicationprocesses');
    }
};