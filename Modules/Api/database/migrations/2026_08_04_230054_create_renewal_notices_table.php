<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('renewal_notices', function (Blueprint $table) {
            $table->id('renewal_notice_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('contact_id');
            $table->integer('notice_period');
            $table->date('created_at')->nullable();

            $table->foreign('application_id')->references('id')->on('applications');
            $table->foreign('contact_id')->references('id')->on('contacts');

            $table->index('application_id');
            $table->index('contact_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_notices');
    }
};