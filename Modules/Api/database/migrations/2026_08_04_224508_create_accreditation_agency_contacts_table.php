<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_agency_contact', function (Blueprint $table) {
            $table->id('accreditation_agency_contact_id');
            $table->integer('agency_id');
            $table->integer('contact_id');
            $table->string('contact_type', 255)->nullable();
            $table->timestamps();

            $table->foreign('contact_id')->references('contact_id')->on('contact');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_agency_contact');
    }
};