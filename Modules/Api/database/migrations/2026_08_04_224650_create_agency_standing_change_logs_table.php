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
        Schema::create('agency_standing_change_log', function (Blueprint $table) {
            $table->integer('log_id');
            $table->integer('agency_id')->nullable();
            $table->integer('institution_id')->nullable();
            $table->integer('pctia_no')->nullable();
            $table->integer('old_status')->nullable();
            $table->integer('new_status')->nullable();
            $table->date('created_at')->nullable();

            $table->comment('Source: EQADM.dbo.agency_standing_change_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agency_standing_change_log');
    }
};