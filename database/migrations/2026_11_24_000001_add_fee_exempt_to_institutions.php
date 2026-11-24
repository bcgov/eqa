<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Institutions exempt from all EQA fees (e.g. Indigenous institutions exempt under the Act).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institutions') && ! Schema::hasColumn('institutions', 'fee_exempt')) {
            Schema::table('institutions', fn (Blueprint $table) => $table->boolean('fee_exempt')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('institutions') && Schema::hasColumn('institutions', 'fee_exempt')) {
            Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn('fee_exempt'));
        }
    }
};
