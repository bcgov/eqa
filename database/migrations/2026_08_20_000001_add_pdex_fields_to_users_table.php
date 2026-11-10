<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds the BC gov SSO (PDEX/Keycloak) identity columns to the users table so a
// federated IDIR/BCeID login can be mapped to a local user. Mirrors bcgov/nrsts.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('guid', 32)->nullable()->unique()->after('id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->boolean('disabled')->default(false)->after('email');
            $table->string('idir_username', 60)->nullable()->after('disabled');
            $table->string('bceid_username', 60)->nullable()->after('idir_username');
            $table->string('idir_user_guid')->nullable()->index()->after('bceid_username');
            $table->string('bceid_user_guid')->nullable()->index()->after('idir_user_guid');
            $table->string('bceid_business_guid')->nullable()->index()->after('bceid_user_guid');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'guid',
                'first_name',
                'last_name',
                'disabled',
                'idir_username',
                'bceid_username',
                'idir_user_guid',
                'bceid_user_guid',
                'bceid_business_guid',
                'deleted_at',
            ]);
        });
    }
};
