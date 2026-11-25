<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Campus locations entered on each application. They belong to the application only: they are not
// prefilled from, linked to or synced with the institution's own campuses, and have no status.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('application_campus_locations')) {
            return;
        }

        Schema::create('application_campus_locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('application_crm_id')->index();
            $table->string('location_name');
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('street1');
            $table->string('street2')->nullable();
            $table->string('city');
            $table->string('province');
            $table->string('postal_code');
            $table->string('country')->nullable();
            $table->boolean('primary_location')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_campus_locations');
    }
};
