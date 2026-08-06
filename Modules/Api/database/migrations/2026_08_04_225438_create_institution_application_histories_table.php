<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_application_histories', function (Blueprint $table) {
            $table->id('institution_application_history_id');
            $table->integer('result_type_id');
            $table->foreign('result_type_id')->references('id')->on('result_types');
            $table->string('event_datetime', 30);
            $table->string('result_type_details', 256)->nullable();
            $table->string('comments', 2480)->nullable();
            $table->integer('institution_id')->nullable();
            $table->foreign('institution_id')->references('id')->on('institutions');
            $table->integer('application_id')->nullable();
            $table->foreign('application_id')->references('id')->on('applications');
            $table->string('created_at', 30)->nullable();
            $table->string('created_by', 80)->nullable();
            $table->string('updated_at', 30)->nullable();
            $table->string('updated_by', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_application_histories');
    }
};