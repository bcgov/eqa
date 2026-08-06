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
        Schema::create('system_configuration', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40);
            $table->string('value', 40);
            $table->string('description', 256)->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('created_at')->nullable();
            $table->string('created_by', 80)->nullable();
            $table->date('updated_at')->nullable();
            $table->string('updated_by', 80)->nullable();
            $table->smallInteger('renewal_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configuration');
    }
};