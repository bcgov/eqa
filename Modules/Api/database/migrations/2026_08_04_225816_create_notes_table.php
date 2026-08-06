<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note', function (Blueprint $table) {
            $table->integer('note_id');
            $table->integer('app_id')->nullable();
            $table->smallInteger('internal')->nullable();
            $table->string('note', 2000)->nullable();
            $table->smallInteger('active')->nullable();
            $table->string('created_at', 30)->nullable();
            $table->string('created_by', 80)->nullable();
            $table->string('updated_at', 30)->nullable();
            $table->string('updated_by', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note');
    }
};