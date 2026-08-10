<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Core EQA entity table. Schema mirrors App\Services\DynamicsDataMigrator (data loader) — keep in sync.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applications')) {
            return;
        }

        Schema::create('applications', function (Blueprint $table): void {
            $table->uuid('crm_id')->primary();
            $table->string('reference')->nullable()->index();
            $table->string('status')->nullable();
            $table->uuid('institution_crm_id')->nullable()->index();
            $table->text('institution_name')->nullable();
            $table->date('application_date')->nullable();
            $table->date('approved_date')->nullable();
            $table->date('not_approved_date')->nullable();
            $table->date('designation_expiry')->nullable();
            $table->decimal('total_due', 12, 2)->nullable();
            $table->text('owner_name')->nullable();
            $table->integer('total_enrolment')->nullable();
            $table->integer('intl_students_permit')->nullable();
            $table->integer('intl_students_other')->nullable();
            $table->integer('in_person_students')->nullable();
            $table->integer('online_students')->nullable();
            $table->string('enrolment_type')->nullable();
            $table->date('review_completion_date')->nullable();
            $table->string('eligibility')->nullable();
            $table->boolean('eqa_good_standing')->nullable();
            $table->boolean('ptib_good_standing')->nullable();
            $table->date('suitability_review_date')->nullable();
            $table->date('resubmission_date')->nullable();
            $table->boolean('designation_decision')->nullable();
            $table->string('workflow_stage')->nullable()->index();
            $table->text('non_approval_reasons')->nullable();
            $table->boolean('receipt_confirmation')->nullable();
            $table->boolean('ready_for_review')->nullable();
            $table->boolean('fees_payment_received')->nullable();
            $table->boolean('process_completed')->nullable();
            $table->boolean('need_additional_details')->nullable();
            $table->boolean('appeal_successful')->nullable();
            $table->boolean('sabc_designation')->nullable();
            $table->boolean('reason_incomplete')->nullable();
            $table->boolean('reason_non_payment')->nullable();
            $table->boolean('reason_withdrawn')->nullable();
            $table->boolean('reason_not_good_standing')->nullable();
            $table->boolean('reason_not_meet_eligibility')->nullable();
            $table->boolean('program_associate_degree')->nullable();
            $table->boolean('program_university_transfer')->nullable();
            $table->boolean('program_bachelors_degree')->nullable();
            $table->boolean('program_graduate_degree')->nullable();
            $table->boolean('program_career_training')->nullable();
            $table->boolean('program_language_training')->nullable();
            $table->boolean('program_theological_education')->nullable();
            $table->boolean('program_trades_apprenticeship')->nullable();
            $table->boolean('media_pamphlet')->nullable();
            $table->boolean('media_website')->nullable();
            $table->boolean('media_brochure')->nullable();
            $table->boolean('media_poster')->nullable();
            $table->boolean('media_banner')->nullable();
            $table->boolean('media_billboard')->nullable();
            $table->text('brand_usage_plan')->nullable();
            $table->text('other_logos_trademarks')->nullable();
            $table->text('affiliates_partners')->nullable();
            $table->boolean('affirm_policy_manual')->nullable();
            $table->boolean('affirm_website_compliance')->nullable();
            $table->boolean('affirm_written_permission')->nullable();
            $table->boolean('affirm_branding_guide')->nullable();
            $table->boolean('affirm_understands_comply')->nullable();
            $table->boolean('affirm_authorized')->nullable();
            $table->text('representative_signature')->nullable();
            $table->decimal('application_fee', 12, 2)->nullable();
            $table->decimal('annual_designation_fee', 12, 2)->nullable();
            $table->text('invoice_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
