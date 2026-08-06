<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contact')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('role')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_role');
    }
};