<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date');
            $table->string('environment', 10)->nullable();
            $table->string('institution_number', 10)->nullable();
            $table->string('user_id', 100)->nullable();
            $table->string('source', 50);
            $table->string('controller', 100);
            $table->text('route_data');
            $table->string('action', 100);
            $table->text('message');
            $table->text('stack_trace');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};