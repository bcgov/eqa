<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_request_task', function (Blueprint $table) {
            $table->id('standing_request_task_id');
            $table->integer('task_id');
            $table->string('connection_url', 1024)->nullable();

            $table->foreign('task_id')->references('id')->on('task');

            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_request_task');
    }
};