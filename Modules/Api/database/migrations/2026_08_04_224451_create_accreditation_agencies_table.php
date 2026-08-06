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
        Schema::create('accreditation_agency', function (Blueprint $table) {
            $table->id('accreditation_agency_id');
            $table->integer('agency_code')->nullable();
            $table->string('agency_name', 45)->nullable();
            $table->string('agency_url', 200)->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('created_at')->nullable();
            $table->string('created_by', 80)->nullable();
            $table->date('updated_at')->nullable();
            $table->string('updated_by', 80)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accreditation_agency');
    }
};