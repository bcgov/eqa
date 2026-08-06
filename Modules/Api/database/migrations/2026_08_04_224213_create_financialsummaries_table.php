<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source lineage: EQADEV_MSCRM/eqa_financialsummaryBase (sql_schema table)
     */
    public function up(): void
    {
        Schema::create('financialsummaries', function (Blueprint $table) {
            $table->uuid('eqa_financialsummary_id')->primary();

            $table->timestampTz('created_on')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('modified_on')->nullable();
            $table->uuid('modified_by')->nullable();
            $table->uuid('created_on_behalf_by')->nullable();
            $table->uuid('modified_on_behalf_by')->nullable();

            $table->uuid('owner_id');
            $table->integer('owner_id_type');
            $table->uuid('owning_business_unit')->nullable();

            $table->integer('statecode');
            $table->integer('statuscode')->nullable();

            $table->binary('version_number')->nullable();
            $table->integer('import_sequence_number')->nullable();
            $table->timestampTz('overridden_created_on')->nullable();
            $table->integer('time_zone_rule_version_number')->nullable();
            $table->integer('utc_conversion_time_zone_code')->nullable();

            $table->string('eqa_invoicenumber', 100)->nullable();

            $table->uuid('processid')->nullable();
            $table->uuid('stageid')->nullable();
            $table->string('traversedpath', 2500)->nullable();
            $table->string('email_address', 512)->nullable();

            $table->uuid('eqa_application_number')->nullable();
            $table->string('eqa_authorization_code', 200)->nullable();
            $table->timestampTz('eqa_cheque_date')->nullable();
            $table->string('eqa_cheque_number', 200)->nullable();
            $table->uuid('eqa_institution_name')->nullable();

            $table->decimal('eqa_invoice_amount', 19, 4)->nullable();
            $table->uuid('transaction_currency_id')->nullable();
            $table->decimal('exchange_rate', 23, 10)->nullable();
            $table->decimal('eqa_invoiceamount_base', 19, 4)->nullable();
            $table->decimal('eqa_invoice_balance', 19, 4)->nullable();
            $table->decimal('eqa_invoicebalance_base', 19, 4)->nullable();
            $table->timestampTz('eqa_invoice_date')->nullable();
            $table->uuid('eqa_invoice_number_id')->nullable();
            $table->string('eqa_invoice_number_text', 100)->nullable();
            $table->integer('eqa_invoice_status')->nullable();

            $table->string('eqa_legacy_invoice_id', 60)->nullable();
            $table->string('eqa_legacy_order_id', 60)->nullable();
            $table->string('eqa_legacy_payment_id', 60)->nullable();
            $table->string('eqa_legacy_refund_id', 60)->nullable();

            $table->decimal('eqa_payment_amount', 19, 4)->nullable();
            $table->decimal('eqa_paymentamount_base', 19, 4)->nullable();
            $table->integer('eqa_payment_method')->nullable();
            $table->timestampTz('eqa_payment_received_date')->nullable();

            $table->timestampTz('eqa_ref_checque_date')->nullable();
            $table->string('eqa_ref_cheque_number', 40)->nullable();

            $table->decimal('eqa_refund_amount', 19, 4)->nullable();
            $table->decimal('eqa_refundamount_base', 19, 4)->nullable();
            $table->timestampTz('eqa_refund_date')->nullable();
            $table->text('eqa_refund_reason')->nullable();

            $table->decimal('eqa_taxes', 19, 4)->nullable();
            $table->decimal('eqa_taxes_base', 19, 4)->nullable();

            $table->string('eqa_transaction_authorization', 200)->nullable();
            $table->string('eqa_transaction_reference', 200)->nullable();

            $table->decimal('eqa_total_charges', 19, 4)->nullable();
            $table->decimal('eqa_totalcharges_base', 19, 4)->nullable();

            $table->uuid('eqa_financial_summaries_id')->nullable();

            $table->index('owner_id');
            $table->index('owning_business_unit');
            $table->index('statecode');
            $table->index('statuscode');
            $table->index('processid');
            $table->index('stageid');
            $table->index('transaction_currency_id');
            $table->index('eqa_application_number');
            $table->index('eqa_institution_name');
            $table->index('eqa_invoice_number_id');
            $table->index('eqa_financial_summaries_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financialsummaries');
    }
};