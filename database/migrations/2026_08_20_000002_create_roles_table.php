<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Application roles and their seed data. Mirrors bcgov/nrsts: users authenticate
// through PDEX and are granted a GUEST role on first login; an admin elevates
// them to a working Ministry/Institution role.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            return;
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('roles')->insert(array_map(
            fn (string $name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now],
            [
                'Super Admin',
                'Ministry Admin',
                'Institution Admin',
                'Ministry User',
                'Institution User',
                'Ministry Guest',
                'Institution Guest',
            ]
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
