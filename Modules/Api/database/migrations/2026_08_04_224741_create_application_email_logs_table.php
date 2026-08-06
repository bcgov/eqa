<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_email_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('application_id')->nullable();
            $table->integer('institution_id')->nullable();
            $table->string('type', 200)->nullable();
            $table->string('event_date', 30)->nullable();
            $table->string('contact_name', 170)->nullable();
            $table->string('contact_title', 100)->nullable();
            $table->string('institution_name', 255)->nullable();
            $table->string('address', 1000)->nullable();
            $table->string('email', 80)->nullable();
            $table->string('institutional_representative', 170)->nullable();
            $table->string('email_title', 100)->nullable();
            $table->string('designation_start_date', 30)->nullable();
            $table->string('designation_end_date', 30)->nullable();
            $table->string('signature', 2048)->nullable();
            $table->string('created_by', 80)->nullable();
            $table->string('updated_at_raw', 30)->nullable();
            $table->string('updated_by', 80)->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('applications')->nullOnDelete();
            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_email_logs');
    }
};