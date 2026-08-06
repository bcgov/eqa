<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_status_histories', function (Blueprint $table) {
            $table->id('appl_status_hist_id');
            $table->unsignedInteger('application_id');
            $table->unsignedInteger('status_id');
            $table->string('comments', 2000)->nullable();
            $table->smallInteger('active')->nullable();
            $table->string('created_at', 30)->nullable();
            $table->string('created_by', 80)->nullable();

            $table->foreign('application_id')->references('id')->on('applications');
            $table->foreign('status_id')->references('id')->on('statuses');

            $table->index('application_id');
            $table->index('status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_histories');
    }
};