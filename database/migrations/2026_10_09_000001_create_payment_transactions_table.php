<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BC Express Pay transaction log (the "Transaction Detail Manager" in the BCEP guides).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            // Worldline trnOrderNumber: must stay <= 10 digits (settlement file width).
            $table->unsignedBigInteger('order_number')->nullable()->unique();
            $table->uuid('invoice_crm_id')->index();
            $table->uuid('application_crm_id')->nullable()->index();
            $table->uuid('institution_crm_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('trn_type', 3)->default('P');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('hash_expires_at');
            $table->string('trn_id')->nullable();
            $table->string('auth_code')->nullable();
            $table->string('message_id')->nullable();
            $table->string('message_text')->nullable();
            $table->string('card_type', 4)->nullable();
            $table->string('trn_date')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
