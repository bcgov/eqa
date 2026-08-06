<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_data_xml_scribe_exports', function (Blueprint $table) {
            $table->id();

            // Core identifiers
            $table->string('student_id')->nullable()->index();
            $table->string('district_id')->nullable()->index();
            $table->string('school_id')->nullable()->index();
            $table->string('school_year')->nullable()->index();
            $table->string('export_batch_id')->nullable()->index();

            // Scribe export metadata
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('export_type')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);

            // Student demographic / record snapshot data
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('state_student_id')->nullable()->index();
            $table->string('local_student_id')->nullable()->index();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('grade_level')->nullable();
            $table->string('enrollment_status')->nullable();
            $table->date('enrollment_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->string('exit_reason')->nullable();

            // Raw payloads
            $table->text('xml_content')->nullable();
            $table->jsonb('source_payload')->nullable();
            $table->jsonb('validation_errors')->nullable();

            // Processing lifecycle
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['export_batch_id', 'status']);
            $table->index(['student_id', 'school_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_data_xml_scribe_exports');
    }
};