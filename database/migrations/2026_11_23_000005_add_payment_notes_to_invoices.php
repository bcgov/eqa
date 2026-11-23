<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Free-text notes the ministry records with a payment received outside Express Pay (EFT, cheque, ...).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'payment_notes')) {
            Schema::table('invoices', fn (Blueprint $table) => $table->text('payment_notes')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'payment_notes')) {
            Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('payment_notes'));
        }
    }
};
