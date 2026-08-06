<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dedesignations', function (Blueprint $table) {
            $table->id();
            $table->integer('de_design_id');
            $table->integer('institution_id');
            $table->integer('de_design_reason_id');
            $table->date('effective_date');
            $table->string('status', 45)->nullable();
            $table->string('comment', 1000)->nullable();
            $table->date('created_at')->nullable();
            $table->string('created_by', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dedesignations');
    }
};