<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_change_log', function (Blueprint $table) {
            $table->increments('audit_change_log_id');
            $table->string('entity', 45)->nullable();
            $table->integer('id');
            $table->string('field', 45)->nullable();
            $table->string('old_value', 2048)->nullable();
            $table->string('new_value', 2048)->nullable();
            $table->date('created_at')->nullable();
            $table->string('created_by', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_change_log');
    }
};