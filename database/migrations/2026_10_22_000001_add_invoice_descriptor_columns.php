<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Invoice descriptor: what each generated invoice charges for (human-readable + itemized).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'line_items')) {
                $table->json('line_items')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $existing = array_values(array_filter(['description', 'line_items'], fn (string $c) => Schema::hasColumn('invoices', $c)));
        if ($existing !== []) {
            Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
