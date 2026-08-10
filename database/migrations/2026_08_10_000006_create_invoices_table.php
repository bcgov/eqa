<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Core EQA entity table. Schema mirrors App\Services\DynamicsDataMigrator (data loader) — keep in sync.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('crm_id')->primary();
            $table->uuid('application_crm_id')->nullable()->index();
            $table->text('application_reference')->nullable();
            $table->uuid('institution_crm_id')->nullable()->index();
            $table->text('institution_name')->nullable();
            $table->string('invoice_number')->nullable()->index();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_amount', 12, 2)->nullable();
            $table->decimal('taxes', 12, 2)->nullable();
            $table->decimal('total_charges', 12, 2)->nullable();
            $table->decimal('invoice_balance', 12, 2)->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->string('invoice_status')->nullable();
            $table->decimal('payment_amount', 12, 2)->nullable();
            $table->date('payment_received_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('transaction_reference')->nullable();
            $table->text('transaction_authorization')->nullable();
            $table->string('cheque_number')->nullable();
            $table->date('cheque_date')->nullable();
            $table->date('refund_date')->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
