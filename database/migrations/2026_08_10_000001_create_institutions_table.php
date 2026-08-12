<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Core EQA entity table. Schema mirrors App\Services\DynamicsDataMigrator (data loader) — keep in sync.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institutions')) {
            return;
        }

        Schema::create('institutions', function (Blueprint $table): void {
            $table->uuid('crm_id')->primary();
            $table->string('institution_number')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('bc_incorporation_number')->nullable();
            $table->string('eqa_status')->nullable();
            $table->string('eqa_standing')->nullable();
            $table->string('ptib_standing')->nullable();
            $table->string('qa_met_through')->nullable();
            $table->string('dli_number')->nullable();
            $table->date('designation_start')->nullable();
            $table->date('designation_expiry')->nullable();
            $table->date('ptib_cert_expiry')->nullable();
            $table->string('website')->nullable();
            $table->string('business_owner')->nullable();
            $table->string('primary_contact')->nullable();
            $table->text('street1')->nullable();
            $table->text('street2')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->integer('total_enrolment')->nullable();
            $table->integer('intl_students_permit')->nullable();
            $table->integer('intl_students_other')->nullable();
            $table->integer('in_person_students')->nullable();
            $table->integer('online_students')->nullable();
            $table->string('enrolment_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
