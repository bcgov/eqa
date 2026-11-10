<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Date an institution stopped being Designated, for the ministry reports. Left NULL on
// migrated rows (unknown); the reports fall back to designation_expiry for those.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institutions') || Schema::hasColumn('institutions', 'de_designated_date')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table): void {
            $table->date('de_designated_date')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('institutions') && Schema::hasColumn('institutions', 'de_designated_date')) {
            Schema::table('institutions', function (Blueprint $table): void {
                $table->dropColumn('de_designated_date');
            });
        }
    }
};
