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
        Schema::create('institution_accreditation', function (Blueprint $table) {
            $table->id('institution_accreditation_id');
            $table->unsignedInteger('institution_id');
            $table->unsignedInteger('accreditation_agency_id');
            $table->string('accreditation_status', 255);
            $table->date('accreditation_start_date');
            $table->date('accreditation_expiry_date')->nullable();
            $table->date('created_at')->nullable();
            $table->string('created_by', 80)->nullable();
            $table->date('updated_at')->nullable();
            $table->string('updated_by', 80)->nullable();

            $table->foreign('accreditation_agency_id')
                ->references('accreditation_agency_id')
                ->on('accreditation_agency');

            $table->index('institution_id');
            $table->index('accreditation_agency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institution_accreditation');
    }
};