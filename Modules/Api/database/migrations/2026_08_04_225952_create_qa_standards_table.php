<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_standards', function (Blueprint $table) {
            $table->id();
            $table->integer('standard_id');
            $table->string('standard_value', 100)->nullable();
            $table->smallInteger('ministry_verification')->nullable();
            $table->smallInteger('pctia_verification')->nullable();
            $table->smallInteger('lc_verification')->nullable();
            $table->smallInteger('hidden')->nullable();
            $table->integer('sort_order')->nullable();
            $table->decimal('fee_amount', 19, 2)->nullable();
            $table->timestamps();

            $table->index('standard_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_standards');
    }
};