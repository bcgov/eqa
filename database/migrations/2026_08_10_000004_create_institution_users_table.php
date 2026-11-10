<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Core EQA entity table. Schema mirrors App\Services\DynamicsDataMigrator (data loader) — keep in sync.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institution_users')) {
            return;
        }

        Schema::create('institution_users', function (Blueprint $table): void {
            $table->uuid('crm_id')->primary();
            $table->uuid('institution_crm_id')->nullable()->index();
            $table->text('institution_name')->nullable();
            $table->text('full_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('role')->nullable();
            $table->string('web_user_name')->nullable();
            $table->boolean('web_user_active')->nullable();
            $table->string('status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_users');
    }
};
