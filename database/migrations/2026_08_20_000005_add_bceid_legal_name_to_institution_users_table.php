<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stores the Business Legal Name returned alongside the BCeID GUIDs when an
// admin runs "Fetch BCeID Data" for an institution contact.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institution_users')) {
            return;
        }

        Schema::table('institution_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('institution_users', 'bceid_business_legal_name')) {
                $table->string('bceid_business_legal_name')->nullable()->after('bceid_business_guid');
            }
            if (! Schema::hasColumn('institution_users', 'bceid_fetched_at')) {
                $table->timestamp('bceid_fetched_at')->nullable()->after('bceid_business_legal_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('institution_users')) {
            return;
        }

        Schema::table('institution_users', function (Blueprint $table): void {
            $table->dropColumn(['bceid_business_legal_name', 'bceid_fetched_at']);
        });
    }
};
