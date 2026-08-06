<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source lineage: EQADM/eqa_status (sql_schema:table)
 * Database.Schema.Table: EQADM.dbo.eqa_status
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->integer('eqa_status_code');
            $table->string('eqa_status_value', 45)->nullable();
            $table->timestamps();

            $table->unique('eqa_status_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};