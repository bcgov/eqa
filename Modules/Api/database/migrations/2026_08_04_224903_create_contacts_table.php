<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADM.dbo.contact (sql_schema) — ref: EQADM/contact
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id('contact_id');
            $table->unsignedInteger('institution_id')->nullable();
            $table->string('title', 100)->nullable();
            $table->string('department', 80)->nullable();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->unsignedInteger('address_id')->nullable();
            $table->string('phone_number_1', 40)->nullable();
            $table->string('phone_number_2', 40)->nullable();
            $table->string('fax', 40)->nullable();
            $table->string('email', 80)->nullable();
            $table->string('signature', 80)->nullable();
            $table->string('password', 45)->nullable();
            $table->smallInteger('temp_password')->default(0);
            $table->unsignedInteger('contact_type_id')->nullable();
            $table->smallInteger('active')->default(0);
            $table->string('created_by', 80)->nullable();
            $table->string('updated_by', 80)->nullable();
            $table->timestamps();

            $table->foreign('address_id')->references('address_id')->on('addresses')->nullOnDelete();
            $table->foreign('contact_type_id')->references('contact_type_id')->on('contact_types')->nullOnDelete();

            $table->index('institution_id');
            $table->index('address_id');
            $table->index('contact_type_id');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};