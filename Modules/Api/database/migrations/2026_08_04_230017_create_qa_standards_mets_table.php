<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_standards_met', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('application_id');
            $table->unsignedInteger('standard_id');
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('application');

            $table->unique(['application_id', 'standard_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_standards_met');
    }
};