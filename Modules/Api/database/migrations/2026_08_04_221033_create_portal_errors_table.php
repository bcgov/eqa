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
        Schema::create('portal_errors', function (Blueprint $table) {
            $table->id();
            $table->string('error_code')->nullable();
            $table->string('message')->nullable();
            $table->text('description')->nullable();
            $table->text('stack_trace')->nullable();
            $table->string('source')->nullable();
            $table->string('request_url')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_errors');
    }
};