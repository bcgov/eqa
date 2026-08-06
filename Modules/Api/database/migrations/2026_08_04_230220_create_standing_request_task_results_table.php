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
        Schema::create('standing_request_task_results', function (Blueprint $table) {
            $table->id('standing_request_task_result_id');
            $table->foreignId('standing_request_task_id')
                ->constrained('standing_request_tasks', 'standing_request_task_id');
            $table->foreignId('institution_id')
                ->constrained('institutions', 'institution_id');
            $table->string('institution_standing_request_status');
            $table->date('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standing_request_task_results');
    }
};