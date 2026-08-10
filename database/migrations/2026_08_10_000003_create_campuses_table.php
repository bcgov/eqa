<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Core EQA entity table. Schema mirrors App\Services\DynamicsDataMigrator (data loader) — keep in sync.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campuses')) {
            return;
        }

        Schema::create('campuses', function (Blueprint $table): void {
            $table->uuid('crm_id')->primary();
            $table->uuid('institution_crm_id')->nullable()->index();
            $table->text('institution_name')->nullable();
            $table->text('name')->nullable();
            $table->text('location_name')->nullable();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->text('website')->nullable();
            $table->text('street1')->nullable();
            $table->text('street2')->nullable();
            $table->text('street3')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->boolean('primary_location')->nullable();
            $table->string('status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campuses');
    }
};
