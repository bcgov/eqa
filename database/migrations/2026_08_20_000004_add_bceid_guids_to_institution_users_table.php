<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Links a BCeID-authenticated user to their institution contact record. The web
// portal scopes to the institution matched on these guids at login.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institution_users')) {
            return;
        }

        Schema::table('institution_users', function (Blueprint $table): void {
            $table->string('bceid_user_guid')->nullable()->index()->after('web_user_name');
            $table->string('bceid_business_guid')->nullable()->index()->after('bceid_user_guid');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('institution_users')) {
            return;
        }

        Schema::table('institution_users', function (Blueprint $table): void {
            $table->dropColumn(['bceid_user_guid', 'bceid_business_guid']);
        });
    }
};
