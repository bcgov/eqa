<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_downloads', function (Blueprint $table) {
            $table->id();
            $table->integer('download_id');
            $table->unsignedInteger('accreditation_agency_id');
            $table->integer('pctia_id');
            $table->integer('status');
            $table->date('created_at')->nullable();

            $table->foreign('accreditation_agency_id')
                ->references('id')
                ->on('accreditation_agencies')
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->index('accreditation_agency_id');
            $table->index('pctia_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_downloads');
    }
};