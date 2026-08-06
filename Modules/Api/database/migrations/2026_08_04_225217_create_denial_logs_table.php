<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('denial_logs', function (Blueprint $table) {
            $table->integer('denial_log_id')->primary();
            $table->integer('institution_id');
            $table->integer('application_id')->nullable();
            $table->integer('reason_code')->nullable();
            $table->date('created_at')->nullable();
            $table->string('created_by', 80)->nullable();

            $table->foreign('application_id')
                ->references('application_id')->on('applications')
                ->nullOnDelete();

            $table->index('institution_id');
            $table->index('application_id');
            $table->index('reason_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denial_logs');
    }
};