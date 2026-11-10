<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The BCeID logon captured on the contact's Dynamics portal profile
// (eqa_portalprofile.eqa_BCeID). Used as the search key for "Fetch BCeID Data".
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institution_users')
            || Schema::hasColumn('institution_users', 'bceid_username')) {
            return;
        }

        Schema::table('institution_users', function (Blueprint $table): void {
            $table->string('bceid_username')->nullable()->index()->after('web_user_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('institution_users')
            || ! Schema::hasColumn('institution_users', 'bceid_username')) {
            return;
        }

        Schema::table('institution_users', function (Blueprint $table): void {
            $table->dropColumn('bceid_username');
        });
    }
};
