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
        Schema::create('dedesignation_reasons', function (Blueprint $table) {
            $table->id();
            $table->integer('de_design_reason_id');
            $table->integer('reason_code');
            $table->string('reason_desc', 80)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dedesignation_reasons');
    }
};