<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Source lineage: EQADM/invoice (sql_schema:table)
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('invoice_id');
            $table->integer('institution_id');
            $table->integer('application_id')->nullable();
            $table->integer('payment_id')->nullable();
            $table->string('invoice_date', 30);
            $table->decimal('invoice_amount', 19, 2);
            $table->string('invoice_status', 30)->nullable();
            $table->string('order_id', 10)->nullable();
            $table->binary('receipt_pdf')->nullable();
            $table->string('source_created_at', 30)->nullable();
            $table->string('created_by', 80)->nullable();
            $table->string('source_updated_at', 30)->nullable();
            $table->string('updated_by', 80)->nullable();
            $table->string('issued_by', 80)->nullable();
            $table->timestamps();

            $table->index('institution_id');

            $table->foreign('application_id')
                ->references('id')->on('applications')
                ->nullOnDelete();

            $table->foreign('payment_id')
                ->references('id')->on('payments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};