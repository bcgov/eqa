<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Application question: "Have you filed your annual report with BC Registry Services?"
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applications') && ! Schema::hasColumn('applications', 'annual_report_filed')) {
            Schema::table('applications', fn (Blueprint $table) => $table->boolean('annual_report_filed')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('applications') && Schema::hasColumn('applications', 'annual_report_filed')) {
            Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('annual_report_filed'));
        }
    }
};
