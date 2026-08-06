<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADM.dbo.education_institution (connector: sql_schema, ref: EQADM/education_institution)
     */
    public function up(): void
    {
        Schema::create('education_institutions', function (Blueprint $table) {
            $table->id();
            $table->integer('institution_id');
            $table->integer('institution_type')->nullable();
            $table->string('institution_name', 255);
            $table->integer('eqa_status_code')->nullable();
            $table->integer('institution_size_id');
            $table->integer('institution_percentage_id');
            $table->string('owner_name', 2048)->nullable();
            $table->string('legal_name', 2048)->nullable();
            $table->string('doing_business_as', 2048)->nullable();
            $table->integer('pictia_id')->nullable();
            $table->string('lc_id', 40)->nullable();
            $table->string('student_percentage_type', 40)->nullable();
            $table->string('institution_size_type', 40)->nullable();
            $table->string('institution_description', 256)->nullable();
            $table->string('institution_signature', 100)->nullable();
            $table->integer('pctia_standing')->nullable();
            $table->integer('ministry_standing')->nullable();
            $table->integer('lc_standing')->nullable();
            $table->string('incorporation_number', 35)->nullable();
            $table->string('start_date', 30)->nullable();
            $table->string('expiry_date', 30)->nullable();
            $table->string('source_created_at', 30)->nullable();
            $table->string('created_by', 80)->nullable();
            $table->string('source_updated_at', 30)->nullable();
            $table->string('updated_by', 80)->nullable();
            $table->timestamps();

            $table->index('institution_size_id');
            $table->index('institution_percentage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_institutions');
    }
};