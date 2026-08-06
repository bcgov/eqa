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
        Schema::create('result_type', function (Blueprint $table) {
            $table->integer('result_type_id');
            $table->integer('event_type_code');
            $table->integer('result_type_code');
            $table->string('result_type_value', 256);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_type');
    }
};