<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->integer('verification_request_id');
            $table->integer('agency_id');
            $table->integer('institution_id');
            $table->date('created_at')->nullable();
            $table->string('created_by', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
    }
};