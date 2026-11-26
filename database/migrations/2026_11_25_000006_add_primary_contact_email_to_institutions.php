<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The ministry records the institution's primary contact email (with the business owner and primary
// contact name) on the institution profile; notifications and the contacts report use it when set.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('institutions') && ! Schema::hasColumn('institutions', 'primary_contact_email')) {
            Schema::table('institutions', fn (Blueprint $table) => $table->string('primary_contact_email')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('institutions') && Schema::hasColumn('institutions', 'primary_contact_email')) {
            Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn('primary_contact_email'));
        }
    }
};
