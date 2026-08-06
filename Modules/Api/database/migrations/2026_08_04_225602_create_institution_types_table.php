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
        Schema::create('institution_type', function (Blueprint $table) {
            $table->integer('institution_type_id');
            $table->string('description', 50)->nullable();

            $table->primary('institution_type_id');
        });

        DB::statement("COMMENT ON TABLE institution_type IS 'Migrated from EQADM.dbo.institution_type'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institution_type');
    }
};