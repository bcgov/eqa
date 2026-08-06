<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADM/eqa_status_history (sql_schema:table)
     */
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id('eqa_status_history_id');
            $table->integer('institution_id')->nullable();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->integer('reason_code')->nullable();
            $table->string('effective_date', 30)->nullable();
            $table->string('comments', 500)->nullable();
            $table->date('created_at')->nullable();
            $table->string('created_by', 80)->nullable();

            $table->foreign('application_id')->references('id')->on('applications')->nullOnDelete();
            $table->foreign('status_id')->references('id')->on('statuses')->nullOnDelete();

            $table->index('institution_id');
            $table->index('application_id');
            $table->index('status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};